<?php

namespace udigroup_globkurier;

class GlobKurierCountries extends GlobKurier{

	const CACHE_OPTION = 'globkurier_countries_cache';
	const CACHE_TTL    = DAY_IN_SECONDS;

	/** @var array<string,array> lista krajow w biezacym zadaniu, per jezyk */
	private static $memory = [];

	public function get(){

		$language = (string) $this->getLanguage();

		if( isset( self::$memory[ $language ] ) ){
			return self::$memory[ $language ];
		}

		// Lista krajow zmienia sie rzadko, a get() bywa wolane wielokrotnie (np. dla kazdego zamowienia w wysylce zbiorczej).
		$cache = get_option( self::CACHE_OPTION, [] );
		$entry = is_array( $cache ) ? ( $cache[ $language ] ?? null ) : null;

		if( $entry && ! empty( $entry[ 'data' ] ) && ( time() - (int) $entry[ 'time' ] ) < self::CACHE_TTL ){
			return self::$memory[ $language ] = $entry[ 'data' ];
		}

		$function = 'countries';
		$method       = 'GET';

		$response = $this->api()->getResponse( $function, NULL, [], $method );

		if( $response[ 'code' ] == 200 && is_array( $response[ 'data' ] ) && ! empty( $response[ 'data' ] ) ){
			$cache = is_array( $cache ) ? $cache : [];
			$cache[ $language ] = [ 'time' => time(), 'data' => $response[ 'data' ] ];
			update_option( self::CACHE_OPTION, $cache, false );

			return self::$memory[ $language ] = $response[ 'data' ];
		}

		// API niedostepne - ostatnia znana lista (nawet przeterminowana) zamiast przerywania strony.
		if( $entry && ! empty( $entry[ 'data' ] ) ){
			return self::$memory[ $language ] = $entry[ 'data' ];
		}

		return [];
	}
	
	/** Tagi dozwolone w wyniku getDropdown() - do wp_kses() przy wypisaniu. */
	const DROPDOWN_ALLOWED_HTML = [ 'option' => [ 'value' => [], 'selected' => [] ] ];

	public function getDropdown( $value = NULL, $type = 'id' ): string{
		
		$countries = $this->get();
		
		$options = [];
		foreach( $countries as $key => $country ){
			
			$selected = '';
			if( $type == 'id' && $value == $country[ 'id' ] ){
				$selected = 'selected';
			}
			
			if( $type == 'code' && strtolower( $value ) == strtolower( $country[ 'isoCode' ] ) ){
				$selected = 'selected';
			}
			
			$options[] = "<option value='".esc_attr($country['id'])."' {$selected}>".esc_html($country['name'].' '.$country['isoCode']).'</option>';
			
		}
		
		return implode( '', $options );
	}
	
	public function getCountryIdByCode($code)
	{
		$countries = $this->get();
		
		foreach ($countries as $country) {
			if ( $country[ 'isoCode' ] == $code ){
				return $country[ 'id' ];
			}
		}
		
		return null;
	}
	
	public function getArray(): array{
		
		$countries = $this->get();
		
		$options = [];
		foreach( $countries as $country ){
			$options[ $country[ 'id' ] ] = $country[ 'name' ] . ' ' . $country[ 'isoCode' ];
		}
		
		return $options;
	}
}