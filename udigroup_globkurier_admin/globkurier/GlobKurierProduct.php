<?php

namespace udigroup_globkurier;

class GlobKurierProduct extends GlobKurier{
	
	public function get( $params, $withToken = TRUE ){
		$params = apply_filters( 'globkurier_get_products_params', $params );
		do_action( 'globkurier_before_get_products', $params );
		
		$showAllProviders = filter_var( $params[ 'globkurier_show_all_providers' ], FILTER_VALIDATE_BOOLEAN );
		$isPickupActive   = filter_var( $params[ 'globkurier_is_pickup_active' ], FILTER_VALIDATE_BOOLEAN );
		$pickupType       = $params[ 'globkurier_pickup_type' ] ?? '';
		
		unset( $params[ 'globkurier_is_pickup_active' ], $params[ 'globkurier_pickup_type' ], $params[ 'globkurier_show_all_providers' ], $params['nonce'] );
				
		$function = 'products';
		$method   = 'GET';
		
		$token = $this->api()->getToken();
		
		$response = $this->api()->getResponse( $function, $token, $params, $method );

		$data = $this->parseProducts( $response[ 'data' ] );
		
		$products = [];
		
		if( $response[ 'code' ] > 204 ){
			$products[ 'status' ] = 'error';
			$products[ 'msg' ]    = $response[ 'data' ][ 'fields' ];
			echo wp_json_encode( $products );
			die;
		} else{
			if( $isPickupActive && ! $showAllProviders ){
				$data = $this->limitProductsToPickupsOnly( $data, $pickupType );
			}
			
			$sortedProducts = $this->sortProducts( $data );
			
			$products[ 'status' ]  = 'success';
			$products[ 'results' ] = $sortedProducts;
		}
		
		do_action( 'globkurier_after_get_products', $products, $sortedProducts );
		
		return $products;
	}
	
	private function parseProducts( $rawProducts ){
		do_action( 'globkurier_before_parse_products', $rawProducts );
		
		if( ! is_array( $rawProducts ) ){
			return [];
		}

		if(isset($rawProducts['fields'])){
			$msg = '';
			foreach ($rawProducts['fields'] ?? [] as $field=>$error) {
				$msg .= $field.': '.$error .' ';
			}
			wp_send_json_error([
				'message'=> __('Wystąpił błąd podczas pobierania danych: ', 'globkurier').$msg
			]);
		}
		
		$products = [];
		foreach( $rawProducts as $type => $productsList ){
			foreach( $productsList as $product ){
				$products[] = apply_filters( 'globkurier_parsing_product', [
					'id'              => $product[ 'id' ],
					'name'            => $product[ 'name' ],
					'carrierName'     => $product[ 'carrierName' ],
					'collectionTypes' => $product[ 'collectionTypes' ],
					'labels'          => $product[ 'labels' ],
					'netPrice'        => (float) $product[ 'netPrice' ],
					'grossPrice'      => (float) $product[ 'grossPrice' ],
					'detailsLink'     => $product[ 'detailsLink' ],
					'carrierLogoLink' => $product[ 'carrierLogoLink' ],
					'deliveryTypeOptions'  => $product['deliveryTypeOptions']
				] );
			}
		}
		
		do_action( 'globkurier_after_parse_products', $products, $rawProducts );
		
		return apply_filters( 'globkurier_parsed_products', $products );
	}
	
	private function sortProducts( $products, $sortBy = 'netPrice' ){
		do_action( 'globkurier_before_sort_products', $products, $sortBy );
		
		usort( $products, static function( $a, $b ) use ( $sortBy ){
			return $b[ $sortBy ] > $a[ $sortBy ] ? - 1 : 1;
		} );
		
		do_action( 'globkurier_after_sort_products', $products, $sortBy );
		
		return apply_filters( 'globkurier_products_sorted', $products );
	}
	
	private function limitProductsToPickupsOnly( $datas, $globkurier_pickup_type ){
		do_action( 'globkurier_before_pickup_products', $datas, $globkurier_pickup_type );
		
		$inpostCarrierName = 'inPost-Paczkomaty';
		$ruchCarrierName   = 'Orlen Paczka';
		
		switch ($globkurier_pickup_type) {
			case 'inpost':
				foreach ($datas as $key => $data) {
					if ($data[ 'carrierName' ] !== $inpostCarrierName) {
						unset($datas[ $key ]);
					}
				}
				break;
			case 'ruch':
				foreach ($datas as $key => $data) {
					// API zwraca 'ORLEN Paczka' - porownanie bez wielkosci liter (wczesniej lista ORLEN byla pusta).
					if (strcasecmp((string) ($data[ 'carrierName' ] ?? ''), $ruchCarrierName) !== 0) {
						unset($datas[ $key ]);
					}
				}
				break;
			default:
				foreach ($datas as $key => $data) {
					if (sanitize_title($data[ 'carrierName' ]) !== $globkurier_pickup_type) {
						unset($datas[ $key ]);
					}
				}
				break;
		}
		
		
		
		
		do_action( 'globkurier_after_pickup_products', $datas, $globkurier_pickup_type );
		
		return apply_filters( 'globkurier_only_pickup_products_filter', $datas );
	}
	
}
