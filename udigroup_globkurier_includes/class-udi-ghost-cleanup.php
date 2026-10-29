<?php

namespace udigroup_globkurier;

if( ! defined( 'ABSPATH' ) ){
	exit;
}

/**
 * Jednorazowe usuniecie pustych postow "ghost" (10168_v2 pkt 6).
 *
 * Wersje do 2.5.x tworzyly nowy post typu globkurier_ghost przy kazdym wyswietleniu metaboxa nadania
 * i nigdy go nie uzywaly - w bazie zostaly setki/tysiace pustych wpisow. Przesylki bez zamowienia WC
 * zapisuja sie tylko na poscie z opcji udigroup_globkurier_ghost_post_id (GlobKurier::getGhostPostID()).
 *
 * Usuwany jest tylko post, ktory spelnia wszystkie warunki (sprawdzane w SQL i ponownie tuz przed usunieciem):
 * - typ globkurier_ghost i tytul nadawany przez wtyczke,
 * - ID inne niz w opcji udigroup_globkurier_ghost_post_id,
 * - brak jakiejkolwiek meta (nazwe meta przesylek mozna zmienic filtrem, wiec nie sprawdzamy jednej nazwy),
 * - utworzony ponad dobe temu (nowy ghost dostaje meta przesylki chwile po utworzeniu - nie wchodzimy w ten moment).
 *
 * Dziala w tle (Action Scheduler), partiami po ID. Wylacznik: filtr globkurier_ghost_cleanup_enabled.
 */
class UDIGroup_GhostCleanup{

	const STATE_OPTION = 'globkurier_ghost_cleanup';
	const HOOK         = 'globkurier_ghost_cleanup';
	const GROUP        = 'globkurier';
	const BATCH        = 100;
	const POST_TYPE    = 'globkurier_ghost';
	const POST_TITLE   = 'globkurier_ghost_post_do_not_delete';
	const KEEP_OPTION  = 'udigroup_globkurier_ghost_post_id';
	const MIN_AGE      = DAY_IN_SECONDS;

	public static function registerHooks(): void{

		add_action( self::HOOK, [ self::class, 'runBatch' ] );
		add_action( 'admin_init', [ self::class, 'maybeSchedule' ] );
	}

	public static function state(): array{

		$state = get_option( self::STATE_OPTION, [] );

		return array_merge( [ 'cursor' => 0, 'deleted' => 0, 'kept' => 0, 'failed' => 0, 'done' => 0 ], is_array( $state ) ? $state : [] );
	}

	private static function needed(): bool{

		return ! self::state()[ 'done' ] && (bool) apply_filters( 'globkurier_ghost_cleanup_enabled', true );
	}

	public static function maybeSchedule(): void{

		if( ! self::needed() ){
			return;
		}

		if( function_exists( 'as_enqueue_async_action' ) && function_exists( 'as_has_scheduled_action' ) ){
			if( ! as_has_scheduled_action( self::HOOK, null, self::GROUP ) ){
				as_enqueue_async_action( self::HOOK, [], self::GROUP );
			}

			return;
		}

		// Bez Action Schedulera: jedna partia na wejscie do panelu, nie czesciej niz co minute.
		if( get_transient( self::HOOK . '_lock' ) ){
			return;
		}
		set_transient( self::HOOK . '_lock', 1, MINUTE_IN_SECONDS );
		self::runBatch();
	}

	public static function runBatch(): void{

		global $wpdb;

		if( ! self::needed() ){
			return;
		}

		$state = self::state();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- jednorazowe sprzatanie partiami po kursorze ID, cache nie ma sensu.
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT `ID` FROM {$wpdb->posts} WHERE `post_type` = %s AND `post_title` = %s AND `ID` > %d ORDER BY `ID` ASC LIMIT %d",
			self::POST_TYPE,
			self::POST_TITLE,
			(int) $state[ 'cursor' ],
			self::BATCH
		) );

		if( ! $ids ){
			$state[ 'done' ]    = 1;
			$state[ 'done_at' ] = time();
			update_option( self::STATE_OPTION, $state, true );

			return;
		}

		$ids = array_map( 'intval', $ids );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- jednorazowe sprzatanie partiami, cache nie ma sensu.
		$withMeta = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT `post_id` FROM {$wpdb->postmeta} WHERE `post_id` IN (" . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')',
			$ids
		) );
		$withMeta = array_flip( array_map( 'intval', $withMeta ?: [] ) );

		// post_date w czasie witryny (post_date_gmt szkicow to 0000-00-00), wiec prog tez w czasie witryny.
		$cutoff = wp_date( 'Y-m-d H:i:s', time() - self::MIN_AGE );

		foreach( $ids as $id ){
			$state[ 'cursor' ] = $id;

			if( isset( $withMeta[ $id ] ) || ! self::isDeletable( $id, $cutoff ) ){
				$state[ 'kept' ]++;
				continue;
			}

			if( wp_delete_post( $id, true ) ){
				$state[ 'deleted' ]++;
			} else{
				$state[ 'failed' ]++;
			}
		}

		update_option( self::STATE_OPTION, $state, true );

		if( function_exists( 'as_enqueue_async_action' ) && self::needed() ){
			as_enqueue_async_action( self::HOOK, [], self::GROUP );
		}
	}

	/**
	 * Ponowne sprawdzenie tuz przed usunieciem (stan z SQL mogl sie zmienic w trakcie partii).
	 */
	private static function isDeletable( int $id, string $cutoff ): bool{

		if( $id === (int) get_option( self::KEEP_OPTION, 0 ) ){
			return false;
		}

		$post = get_post( $id );

		if( ! $post || $post->post_type !== self::POST_TYPE || $post->post_title !== self::POST_TITLE ){
			return false;
		}

		if( (string) $post->post_date === '' || $post->post_date >= $cutoff ){
			return false;
		}

		return empty( get_post_meta( $id ) );
	}
}
