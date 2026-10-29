<?php

namespace udigroup_globkurier;

use Automattic\WooCommerce\Utilities\OrderUtil;

if( ! defined( 'ABSPATH' ) ){
	exit;
}

/**
 * Historia przesylek nadanych przez GlobKurier.
 *
 * Wpisy (zserializowane tablice pod kluczem globkurier_orders) moga lezec w dwoch miejscach:
 * - wp_postmeta: wersje do 2.5.4 zapisywaly tam zawsze (przy HPOS na poscie-placeholderze o ID zamowienia),
 *   tam tez trafiaja przesylki bez zamowienia WC (ghost post),
 * - wc_orders_meta: zapis przez API zamowien WC przy HPOS oraz dane skopiowane przez migracje WooCommerce.
 * Odczyt zawsze laczy oba zrodla i usuwa duplikaty, wiec nie zalezy od stanu migracji ani trybu HPOS.
 */
class UDIGroup_ShipmentHistory{

	const MIGRATION_OPTION = 'globkurier_history_hpos_migration';
	const MIGRATION_HOOK   = 'globkurier_history_hpos_migration';
	const MIGRATION_GROUP  = 'globkurier';
	const MIGRATION_BATCH  = 50;

	/** Indeks historii (wlasna tabela) - patrz sekcja "Indeks historii" nizej. */
	const INDEX_DB_VERSION        = 1;
	const INDEX_DB_VERSION_OPTION = 'globkurier_shipments_db_version';
	const INDEX_OPTION            = 'globkurier_shipments_index';
	const INDEX_HOOK              = 'globkurier_shipments_index';
	const INDEX_BATCH             = 200;
	const INDEX_MAX_ATTEMPTS      = 3;

	private static $hposMetaTable;

	public static function metaName(): string{

		return (string) apply_filters( 'globkurier_wc_order_meta_name', 'globkurier_orders' );
	}

	/**
	 * Tabela meta zamowien HPOS, jesli istnieje (niezaleznie od tego, czy HPOS jest wlaczony).
	 */
	public static function hposMetaTable(): ?string{

		global $wpdb;

		if( self::$hposMetaTable === null ){
			$table               = $wpdb->prefix . 'wc_orders_meta';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- sprawdzenie istnienia tabeli HPOS, wynik w polu statycznym na czas zadania.
			self::$hposMetaTable = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table ? $table : '';
		}

		return self::$hposMetaTable ?: null;
	}

	/**
	 * Przesylki zamowienia WC, od najstarszej.
	 */
	public static function forOrder( $orderId ): array{

		$orderId = absint( $orderId );
		if( ! $orderId ){
			return [];
		}

		return array_reverse( array_column( self::normalize( self::fetchRows( $orderId ) ), 'entry' ) );
	}

	/**
	 * Strona listy "Historia przesylek": najnowsze na gorze.
	 *
	 * @return array{total:int, items:array<int, array{entry:array, owner_id:int}>}
	 */
	public static function query( string $search, int $start, int $length ): array{

		if( self::indexReady() ){
			return self::queryIndex( $search, max( 0, $start ), max( 1, $length ) );
		}

		$items = self::normalize( self::fetchRows( null, $search ) );

		return [
			'total' => count( $items ),
			'items' => array_slice( $items, max( 0, $start ), max( 1, $length ) ),
		];
	}

	/**
	 * Wszystkie przesylki, najnowsze na gorze.
	 *
	 * @return array<int, array{entry:array, owner_id:int}>
	 */
	public static function all(): array{

		if( self::indexReady() ){
			return self::queryIndex( '', 0, PHP_INT_MAX )[ 'items' ];
		}

		return self::normalize( self::fetchRows() );
	}

	/**
	 * Surowe wiersze z obu tabel: najpierw wp_postmeta, potem wc_orders_meta, w kazdej w kolejnosci zapisu.
	 */
	private static function fetchRows( ?int $ownerId = null, string $search = '' ): array{

		global $wpdb;

		// Wlasciciel 0 = wszystkie zamowienia; kazde slowo wyszukiwania to osobny warunek LIKE.
		$terms = preg_split( '/\s+/u', trim( $search ), -1, PREG_SPLIT_NO_EMPTY );
		$args  = array_merge(
			[ self::metaName(), (int) $ownerId, (int) $ownerId ],
			array_map( static function( $term ) use ( $wpdb ){
				return '%' . $wpdb->esc_like( $term ) . '%';
			}, $terms )
		);

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- wlasne meta wtyczki czytane z obu magazynow zamowien naraz (wp_postmeta i HPOS), czego API WC nie oferuje; wynik musi odzwierciedlac biezace zapisy.
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- liczba placeholderow LIKE = liczba slow wyszukiwania, $args budowane z tej samej listy.

		// wp_postmeta tylko z zamowien i ghost posta - ten sam klucz meta na zwyklym wpisie (np. dodany przez
		// autora w "Wlasnych polach") nie moze trafic do historii.
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT `post_id` AS owner_id, `meta_value` FROM {$wpdb->postmeta} INNER JOIN {$wpdb->posts} p ON p.`ID` = `post_id` AND p.`post_type` IN ('shop_order', 'shop_order_placehold', 'globkurier_ghost') WHERE `meta_key` = %s AND (%d = 0 OR `post_id` = %d)"
			. implode( '', array_fill( 0, count( $terms ), ' AND `meta_value` LIKE %s' ) ) . ' ORDER BY `meta_id` ASC',
			...$args
		), ARRAY_A ) ?: [];

		if( self::hposMetaTable() ){
			$rows = array_merge( $rows, $wpdb->get_results( $wpdb->prepare(
				"SELECT `order_id` AS owner_id, `meta_value` FROM {$wpdb->prefix}wc_orders_meta WHERE `meta_key` = %s AND (%d = 0 OR `order_id` = %d)"
				. implode( '', array_fill( 0, count( $terms ), ' AND `meta_value` LIKE %s' ) ) . ' ORDER BY `id` ASC',
				...$args
			), ARRAY_A ) ?: [] );
		}

		// phpcs:enable

		return $rows;
	}

	/**
	 * Rozpakowanie wpisu historii bez tworzenia obiektow PHP - wpis to tablica wartosci skalarnych.
	 * W bazie wartosc jest zserializowana dwukrotnie (maybe_serialize przed zapisem meta), stad do dwoch przebiegow.
	 */
	public static function unpack( $value ): ?array{

		$value = $value->value ?? $value;

		for( $i = 0; $i < 2 && is_string( $value ); $i++ ){
			$decoded = @unserialize( trim( $value ), [ 'allowed_classes' => false ] );
			if( $decoded === false && trim( $value ) !== 'b:0;' ){
				break;
			}
			$value = $decoded;
		}

		return is_array( $value ) ? $value : null;
	}

	/**
	 * Rozpakowanie, usuniecie duplikatow (po numerze GK) i sortowanie po dacie nadania malejaco.
	 * Przy rownych datach pozniej zapisany wpis jest wyzej.
	 *
	 * @param array $rows wiersze ['owner_id' => int, 'meta_value' => mixed] w kolejnosci zapisu
	 */
	public static function normalize( array $rows ): array{

		$items = [];
		foreach( $rows as $row ){
			$entry = self::unpack( $row[ 'meta_value' ] ?? null );
			if( ! is_array( $entry ) ){
				continue;
			}

			$key = self::identity( $entry );
			if( ! isset( $items[ $key ] ) ){
				$items[ $key ] = [ 'entry' => $entry, 'owner_id' => (int) ( $row[ 'owner_id' ] ?? 0 ) ];
			}
		}

		$items     = array_values( $items );
		$dates     = array_map( function( $item ){ return (int) ( $item[ 'entry' ][ 'date' ] ?? 0 ); }, $items );
		$positions = array_keys( $items );
		array_multisort( $dates, SORT_DESC, $positions, SORT_DESC, $items );

		return $items;
	}

	/**
	 * Zamowienia WC dla listy ID jednym zapytaniem (ghost post i usuniete zamowienia nie wystepuja w wyniku).
	 *
	 * @return array<int, \WC_Order>
	 */
	public static function ordersById( array $ids ): array{

		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		if( ! $ids || ! function_exists( 'wc_get_orders' ) ){
			return [];
		}

		$orders = wc_get_orders( [
			'post__in' => $ids,
			'limit'    => -1,
			'status'   => array_merge( array_keys( wc_get_order_statuses() ), [ 'trash' ] ),
		] );

		$byId = [];
		foreach( $orders as $order ){
			$byId[ $order->get_id() ] = $order;
		}

		return $byId;
	}

	/*
	 * Jednorazowa migracja: kopiuje przesylki z wp_postmeta do wc_orders_meta dla zamowien, ktore sa w HPOS.
	 * Dziala w tle (Action Scheduler), partiami, od miejsca przerwania. Niczego nie usuwa.
	 * Kopiuje surowe wiersze, bez $order->save() - zapis przez API zamowienia zmienilby date modyfikacji
	 * setek starych zamowien, co integracje (ERP, marketplace) moglyby odczytac jako zmiane zamowienia.
	 */

	public static function registerHooks(): void{

		add_action( self::MIGRATION_HOOK, [ self::class, 'runMigrationBatch' ] );
		add_action( 'admin_init', [ self::class, 'maybeScheduleMigration' ] );

		add_action( self::INDEX_HOOK, [ self::class, 'runIndexBatch' ] );
		add_action( 'admin_init', [ self::class, 'maybeScheduleIndex' ] );
	}

	public static function migrationState(): array{

		$state = get_option( self::MIGRATION_OPTION, [] );

		return array_merge( [ 'cursor' => 0, 'copied' => 0, 'skipped' => 0, 'done' => 0 ], is_array( $state ) ? $state : [] );
	}

	private static function migrationNeeded(): bool{

		if( self::migrationState()[ 'done' ] ){
			return false;
		}

		// Bez HPOS meta zamowien to wp_postmeta - nie ma czego kopiowac. Stan sprawdzamy ponownie przy kolejnym wejsciu.
		return class_exists( OrderUtil::class ) && OrderUtil::custom_orders_table_usage_is_enabled() && self::hposMetaTable();
	}

	public static function maybeScheduleMigration(): void{

		if( ! self::migrationNeeded() ){
			return;
		}

		if( function_exists( 'as_enqueue_async_action' ) && function_exists( 'as_has_scheduled_action' ) ){
			if( ! as_has_scheduled_action( self::MIGRATION_HOOK, null, self::MIGRATION_GROUP ) ){
				as_enqueue_async_action( self::MIGRATION_HOOK, [], self::MIGRATION_GROUP );
			}

			return;
		}

		// Bez Action Schedulera: jedna partia na wejscie do panelu, nie czesciej niz co minute.
		if( get_transient( self::MIGRATION_HOOK . '_lock' ) ){
			return;
		}
		set_transient( self::MIGRATION_HOOK . '_lock', 1, MINUTE_IN_SECONDS );
		self::runMigrationBatch();
	}

	public static function runMigrationBatch(): void{

		global $wpdb;

		if( ! self::migrationNeeded() ){
			return;
		}

		$state       = self::migrationState();
		$metaName    = self::metaName();
		$metaTable   = self::hposMetaTable();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- jednorazowa migracja partiami po kursorze meta_id, cache nie ma sensu.
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT pm.`meta_id`, pm.`post_id`, pm.`meta_value` FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->prefix}wc_orders o ON o.`id` = pm.`post_id` WHERE pm.`meta_key` = %s AND pm.`meta_id` > %d ORDER BY pm.`meta_id` ASC LIMIT %d",
			$metaName,
			(int) $state[ 'cursor' ],
			self::MIGRATION_BATCH
		), ARRAY_A );

		if( ! $rows ){
			$state[ 'done' ]    = 1;
			$state[ 'done_at' ] = time();
			update_option( self::MIGRATION_OPTION, $state, true );

			return;
		}

		$orderIds = array_values( array_unique( array_map( 'intval', array_column( $rows, 'post_id' ) ) ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- jednorazowa migracja partiami po kursorze meta_id, cache nie ma sensu.
		$existing = $wpdb->get_results( $wpdb->prepare(
			"SELECT `order_id` AS owner_id, `meta_value` FROM {$wpdb->prefix}wc_orders_meta WHERE `meta_key` = %s AND `order_id` IN (" . implode( ',', array_fill( 0, count( $orderIds ), '%d' ) ) . ')',
			array_merge( [ $metaName ], $orderIds )
		), ARRAY_A );

		$known = [];
		foreach( self::normalize( $existing ?: [] ) as $item ){
			$known[ self::identity( $item[ 'entry' ] ) ] = true;
		}

		$touched = [];
		$failed  = false;
		foreach( $rows as $row ){
			$entry    = self::unpack( $row[ 'meta_value' ] );
			$identity = is_array( $entry ) ? self::identity( $entry ) : null;

			if( $identity === null || isset( $known[ $identity ] ) ){
				$state[ 'skipped' ]++;
			} else{
				// Wartosc kopiowana bajt w bajt - ten sam format co przy zapisie przez API zamowienia.
				// Surowy INSERT zamiast $order->save(), zeby migracja nie zmieniala date_modified zamowien.
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- jednorazowa migracja meta do HPOS (powod wyzej).
				$inserted = $wpdb->insert( $metaTable, [
					'order_id'   => (int) $row[ 'post_id' ],
					'meta_key'   => $metaName,
					'meta_value' => $row[ 'meta_value' ],
				] );
				// phpcs:enable

				if( ! $inserted ){
					// Blad bazy: zostawiamy kursor przed tym wierszem, kolejne uruchomienie sprobuje ponownie.
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- celowy log bledu operacyjnego (przesylka juz oplacona / migracja), nie debug.
					error_log( '[globkurier] Migracja historii przesylek do HPOS przerwana na meta_id ' . $row[ 'meta_id' ] . ': ' . $wpdb->last_error );
					$failed = true;
					break;
				}

				$known[ $identity ]               = true;
				$touched[ (int) $row[ 'post_id' ] ] = true;
				$state[ 'copied' ]++;
			}

			$state[ 'cursor' ] = (int) $row[ 'meta_id' ];
		}

		update_option( self::MIGRATION_OPTION, $state, true );

		self::clearOrderCache( array_keys( $touched ) );

		// Po bledzie nie ponawiamy od razu - kolejna proba przy nastepnym wejsciu do panelu (admin_init).
		if( ! $failed && function_exists( 'as_enqueue_async_action' ) && self::migrationNeeded() ){
			as_enqueue_async_action( self::MIGRATION_HOOK, [], self::MIGRATION_GROUP );
		}
	}

	/*
	 * Indeks historii: wlasna tabela {prefix}globkurier_shipments z jednym wierszem na przesylke (data nadania
	 * w kolumnie z indeksem), zeby lista i protokoly stronicowaly w SQL zamiast rozpakowywac i sortowac w PHP
	 * wszystkie wpisy przy kazdym odswiezeniu.
	 *
	 * Meta zamowien zostaje zrodlem danych (zapis bez zmian); tabela to pochodna kopia:
	 * - nowe przesylki trafiaja do niej przy zapisie (saveOrderToWc),
	 * - istniejace uzupelnia jednorazowo zadanie w tle (partiami, od miejsca przerwania, niczego nie usuwa),
	 * - lista czyta z tabeli dopiero po weryfikacji, ze kazda przesylka z meta jest w tabeli; do tego czasu,
	 *   a takze gdy tabeli nie da sie utworzyc, dziala dotychczasowy odczyt z meta.
	 * Przesylka usunietego zamowienia zostaje w tabeli (byla nadana i oplacona) - decyzja z 10168_v2.
	 */

	public static function indexTable(): string{

		global $wpdb;

		return $wpdb->prefix . 'globkurier_shipments';
	}

	public static function indexState(): array{

		$state = get_option( self::INDEX_OPTION, [] );

		return array_merge(
			[ 'phase' => 'postmeta', 'cursor' => 0, 'processed' => 0, 'skipped' => 0, 'attempts' => 0, 'done' => 0, 'failed' => 0 ],
			is_array( $state ) ? $state : []
		);
	}

	/** Lista czyta z tabeli tylko po zakonczonym i zweryfikowanym uzupelnieniu (sama opcja, bez zapytan). */
	public static function indexReady(): bool{

		return (int) get_option( self::INDEX_DB_VERSION_OPTION, 0 ) === self::INDEX_DB_VERSION && ! empty( self::indexState()[ 'done' ] );
	}

	/**
	 * Tworzy tabele (dbDelta), jesli jej wersja jest nieaktualna. False, gdy sie nie udalo (np. brak uprawnienia
	 * CREATE) - wtedy wszystko dziala jak dotad, bez indeksu.
	 */
	public static function installIndex(): bool{

		global $wpdb;

		if( (int) get_option( self::INDEX_DB_VERSION_OPTION, 0 ) === self::INDEX_DB_VERSION ){
			return true;
		}

		// Nieudana proba - kolejna najwczesniej za godzine, zeby nie powtarzac dbDelta przy kazdym wejsciu do panelu.
		if( get_transient( self::INDEX_OPTION . '_install_failed' ) ){
			return false;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table = self::indexTable();

		dbDelta( "CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  identity varbinary(191) NOT NULL,
  owner_id bigint(20) unsigned NOT NULL DEFAULT 0,
  shipped_at bigint(20) NOT NULL DEFAULT 0,
  entry longtext NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY identity (identity),
  KEY shipped (shipped_at,id),
  KEY owner_id (owner_id)
) {$wpdb->get_charset_collate()};" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- sprawdzenie, czy dbDelta utworzylo tabele (jednorazowo przy instalacji indeksu).
		if( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ){
			set_transient( self::INDEX_OPTION . '_install_failed', 1, HOUR_IN_SECONDS );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- celowy log bledu operacyjnego (indeks historii niedostepny, lista dziala po staremu), nie debug.
			error_log( '[globkurier] Nie udalo sie utworzyc tabeli ' . $table . ' (historia przesylek dziala bez indeksu): ' . $wpdb->last_error );

			return false;
		}

		update_option( self::INDEX_DB_VERSION_OPTION, self::INDEX_DB_VERSION, true );

		return true;
	}

	/**
	 * Dopisuje przesylki do indeksu (duplikaty po identity() sa pomijane - pierwszy zapis wygrywa, jak w normalize()).
	 *
	 * @param array<int, array{owner_id:int, entry:array}> $items w kolejnosci zapisu
	 */
	private static function insertIndexRows( array $items ): bool{

		global $wpdb;

		if( ! $items ){
			return true;
		}

		// Po jednym wierszu: uzupelnianie jest jednorazowe, a pojedyncze INSERT IGNORE z placeholderami jest najprostsze.
		foreach( $items as $item ){
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- wlasna tabela indeksu historii, zapis.
			$result = $wpdb->query( $wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->prefix}globkurier_shipments (`identity`, `owner_id`, `shipped_at`, `entry`) VALUES (%s, %d, %d, %s)",
				self::indexIdentity( $item[ 'entry' ] ),
				(int) $item[ 'owner_id' ],
				(int) ( $item[ 'entry' ][ 'date' ] ?? 0 ),
				serialize( $item[ 'entry' ] )
			) );

			if( $result === false ){
				return false;
			}
		}

		return true;
	}

	/**
	 * Zapis nowej przesylki do indeksu (GlobKurierOrder::saveOrderToWc, po zapisie meta). Blad indeksu nigdy nie
	 * przerywa nadania - przesylka jest juz oplacona, a meta (zrodlo danych) zapisane; brakujacy wiersz wykryje
	 * weryfikacja albo lista wroci do odczytu z meta.
	 */
	public static function indexShipment( int $ownerId, $metaValue ): void{

		try{
			$entry = self::unpack( $metaValue );

			if( ! is_array( $entry ) || (int) get_option( self::INDEX_DB_VERSION_OPTION, 0 ) !== self::INDEX_DB_VERSION ){
				return;
			}

			if( ! self::insertIndexRows( [ [ 'owner_id' => $ownerId, 'entry' => $entry ] ] ) ){
				throw new \RuntimeException( $GLOBALS[ 'wpdb' ]->last_error ?? 'insert failed' );
			}
		} catch( \Throwable $e ){
			// Indeks w uzyciu: lista przechodzi na odczyt z meta, dopoki kolejna weryfikacja nie potwierdzi kompletnosci.
			// W trakcie uzupelniania nic nie zmieniamy - wpis jest w meta, wiec uzupelnianie i tak do niego dojdzie.
			$state = self::indexState();
			if( $state[ 'done' ] ){
				$state[ 'done' ]  = 0;
				$state[ 'phase' ] = 'verify';
				update_option( self::INDEX_OPTION, $state, true );
			}

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- celowy log bledu operacyjnego (przesylka oplacona, indeks bez wpisu), nie debug.
			error_log( '[globkurier] Zapis przesylki do indeksu historii nieudany: ' . $e->getMessage() );
		}
	}

	private static function indexNeeded(): bool{

		$state = self::indexState();

		return ! $state[ 'done' ] && ! $state[ 'failed' ];
	}

	public static function maybeScheduleIndex(): void{

		if( ! self::indexNeeded() || ! self::installIndex() ){
			return;
		}

		if( function_exists( 'as_enqueue_async_action' ) && function_exists( 'as_has_scheduled_action' ) ){
			if( ! as_has_scheduled_action( self::INDEX_HOOK, null, self::MIGRATION_GROUP ) ){
				as_enqueue_async_action( self::INDEX_HOOK, [], self::MIGRATION_GROUP );
			}

			return;
		}

		// Bez Action Schedulera: jedna partia na wejscie do panelu, nie czesciej niz co minute.
		if( get_transient( self::INDEX_HOOK . '_lock' ) ){
			return;
		}
		set_transient( self::INDEX_HOOK . '_lock', 1, MINUTE_IN_SECONDS );
		self::runIndexBatch();
	}

	/**
	 * Jedna partia uzupelniania: najpierw wp_postmeta (po meta_id), potem wc_orders_meta (po id) - ta sama kolejnosc
	 * co fetchRows(), wiec duplikat zostaje przy tym samym zamowieniu co dotad, a rowne daty sortuja sie tak samo.
	 * Na koniec weryfikacja kompletnosci.
	 */
	public static function runIndexBatch(): void{

		global $wpdb;

		if( ! self::indexNeeded() || ! self::installIndex() ){
			return;
		}

		$state = self::indexState();

		if( $state[ 'phase' ] === 'verify' ){
			self::verifyIndex( $state );

			return;
		}

		$metaName = self::metaName();

		if( $state[ 'phase' ] === 'postmeta' ){
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- jednorazowe uzupelnienie indeksu partiami po kursorze meta_id, cache nie ma sensu.
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT pm.`meta_id` AS cursor_id, pm.`post_id` AS owner_id, pm.`meta_value` FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.`ID` = pm.`post_id` AND p.`post_type` IN ('shop_order', 'shop_order_placehold', 'globkurier_ghost') WHERE pm.`meta_key` = %s AND pm.`meta_id` > %d ORDER BY pm.`meta_id` ASC LIMIT %d",
				$metaName,
				(int) $state[ 'cursor' ],
				self::INDEX_BATCH
			), ARRAY_A );
		} elseif( self::hposMetaTable() ){
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- jednorazowe uzupelnienie indeksu partiami po kursorze id, cache nie ma sensu.
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT `id` AS cursor_id, `order_id` AS owner_id, `meta_value` FROM {$wpdb->prefix}wc_orders_meta WHERE `meta_key` = %s AND `id` > %d ORDER BY `id` ASC LIMIT %d",
				$metaName,
				(int) $state[ 'cursor' ],
				self::INDEX_BATCH
			), ARRAY_A );
		} else{
			$rows = [];
		}

		if( ! $rows ){
			$state[ 'phase' ]  = $state[ 'phase' ] === 'postmeta' ? 'hpos' : 'verify';
			$state[ 'cursor' ] = 0;
			update_option( self::INDEX_OPTION, $state, true );
			self::scheduleNextIndexBatch();

			return;
		}

		$items = [];
		foreach( $rows ?: [] as $row ){
			$entry = self::unpack( $row[ 'meta_value' ] );
			if( is_array( $entry ) ){
				$items[] = [ 'owner_id' => (int) $row[ 'owner_id' ], 'entry' => $entry ];
			} else{
				$state[ 'skipped' ]++;
			}
		}

		if( ! self::insertIndexRows( $items ) ){
			// Blad bazy: kursor zostaje, kolejna proba przy nastepnym wejsciu do panelu (admin_init).
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- celowy log bledu operacyjnego, nie debug.
			error_log( '[globkurier] Uzupelnianie indeksu historii przerwane (' . $state[ 'phase' ] . ', kursor ' . $state[ 'cursor' ] . '): ' . $wpdb->last_error );

			return;
		}

		$state[ 'processed' ] += count( $items );
		$state[ 'cursor' ]   = (int) end( $rows )[ 'cursor_id' ];
		update_option( self::INDEX_OPTION, $state, true );

		self::scheduleNextIndexBatch();
	}

	private static function scheduleNextIndexBatch(): void{

		if( function_exists( 'as_enqueue_async_action' ) && self::indexNeeded() ){
			as_enqueue_async_action( self::INDEX_HOOK, [], self::MIGRATION_GROUP );
		}
	}

	/**
	 * Kazda przesylka z meta (to, co dzis pokazuje lista) musi byc w tabeli. Brak -> ponowne uzupelnienie od poczatku
	 * (INSERT IGNORE dopisze brakujace), po INDEX_MAX_ATTEMPTS probach indeks zostaje wylaczony (lista z meta).
	 */
	private static function verifyIndex( array $state ): void{

		global $wpdb;

		$expected = [];
		foreach( self::normalize( self::fetchRows() ) as $item ){
			$expected[] = self::indexIdentity( $item[ 'entry' ] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- jednorazowa weryfikacja wlasnej tabeli indeksu.
		$indexed = $wpdb->get_col( $wpdb->prepare( "SELECT `identity` FROM {$wpdb->prefix}globkurier_shipments WHERE `id` > %d", 0 ) );

		$missing = array_diff( $expected, is_array( $indexed ) ? $indexed : [] );

		$state[ 'verified_at' ] = time();
		$state[ 'missing' ]     = count( $missing );

		if( ! $missing ){
			// Licznik prob dotyczy jednej serii - pojedyncze bledy zapisu w ciagu lat nie moga wylaczyc indeksu na stale.
			$state[ 'done' ]     = 1;
			$state[ 'attempts' ] = 0;
		} elseif( ++$state[ 'attempts' ] >= self::INDEX_MAX_ATTEMPTS ){
			$state[ 'failed' ] = 1;
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- celowy log bledu operacyjnego (lista zostaje przy odczycie z meta), nie debug.
			error_log( '[globkurier] Indeks historii niekompletny po ' . $state[ 'attempts' ] . ' probach (brakuje ' . count( $missing ) . ' przesylek) - historia czyta z meta.' );
		} else{
			$state[ 'phase' ]  = 'postmeta';
			$state[ 'cursor' ] = 0;
		}

		update_option( self::INDEX_OPTION, $state, true );

		self::scheduleNextIndexBatch();
	}

	/**
	 * Strona historii z indeksu: stronicowanie i wyszukiwanie w SQL (wyszukiwanie jak dotad - kazde slowo LIKE
	 * na zserializowanym wpisie).
	 *
	 * @return array{total:int, items:array<int, array{entry:array, owner_id:int}>}
	 */
	private static function queryIndex( string $search, int $start, int $length ): array{

		global $wpdb;

		$terms = preg_split( '/\s+/u', trim( $search ), -1, PREG_SPLIT_NO_EMPTY );
		$likes = array_map( static function( $term ) use ( $wpdb ){
			return '%' . $wpdb->esc_like( $term ) . '%';
		}, $terms );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- wlasna tabela indeksu; liczba placeholderow LIKE = liczba slow wyszukiwania (jak w fetchRows()); wynik musi odzwierciedlac biezace zapisy.
		$total = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}globkurier_shipments WHERE `id` > %d"
			. implode( '', array_fill( 0, count( $likes ), ' AND `entry` LIKE %s' ) ),
			array_merge( [ 0 ], $likes )
		) );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT `owner_id`, `entry` FROM {$wpdb->prefix}globkurier_shipments WHERE `id` > %d"
			. implode( '', array_fill( 0, count( $likes ), ' AND `entry` LIKE %s' ) ) . ' ORDER BY `shipped_at` DESC, `id` DESC LIMIT %d OFFSET %d',
			array_merge( [ 0 ], $likes, [ $length, $start ] )
		), ARRAY_A ) ?: [];
		// phpcs:enable

		$items = [];
		foreach( $rows as $row ){
			$entry = self::unpack( $row[ 'entry' ] );
			if( is_array( $entry ) ){
				$items[] = [ 'entry' => $entry, 'owner_id' => (int) $row[ 'owner_id' ] ];
			}
		}

		return [ 'total' => $total, 'items' => $items ];
	}

	/** identity() dla kolumny varbinary(191) (porownanie bajt w bajt, jak w PHP) - dluzsze jako skrot. */
	private static function indexIdentity( array $entry ): string{

		$identity = self::identity( $entry );

		return strlen( $identity ) > 191 ? 'x:' . md5( $identity ) : $identity;
	}

	private static function identity( array $entry ): string{

		return ! empty( $entry[ 'number' ] ) ? 'n:' . $entry[ 'number' ] : ( ! empty( $entry[ 'hash' ] ) ? 'h:' . $entry[ 'hash' ] : 'v:' . md5( serialize( $entry ) ) );
	}

	/**
	 * Po bezposrednim zapisie do wc_orders_meta - zeby obiekty zamowien z cache (np. Redis) mialy nowe meta.
	 */
	private static function clearOrderCache( array $orderIds ): void{

		if( ! $orderIds || ! function_exists( 'wc_get_container' ) ){
			return;
		}

		try{
			$dataStore = wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore::class );
			if( method_exists( $dataStore, 'clear_cached_data' ) ){
				$dataStore->clear_cached_data( $orderIds );
			}

			$orderCache = wc_get_container()->get( \Automattic\WooCommerce\Caches\OrderCache::class );
			foreach( $orderIds as $orderId ){
				$orderCache->remove( $orderId );
			}
		} catch( \Throwable $e ){
			// Starsze WooCommerce bez tych klas - cache wygasnie sam.
		}
	}
}
