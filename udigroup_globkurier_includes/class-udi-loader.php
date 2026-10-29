<?php

namespace udigroup_globkurier;

class UDIGroup_Loader{
	
	protected $actions;
	
	protected $filters;
	
	protected $shortcodes;
	
	protected $menuPages;
	
	protected $menuSubPages;
	
	public function __construct(){
		$this->actions      = [];
		$this->filters      = [];
		$this->shortcodes   = [];
		$this->menuPages    = [];
		$this->menuSubPages = [];
	}
	
	public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ){
		$this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
	}
	
	public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ){
		$this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
	}
	
	public function add_shortcode( $tag, $component, $callback ){
		$this->shortcodes = $this->add( $this->shortcodes, $tag, $component, $callback, 0, 0 );
	}
	
	public function add_menu_page( $component, $pageTitle, $menuTitle, $menuSlug, $iconUrl = '', $position = 5, $capability = 'manage_options' ){
		array_push( $this->menuPages, [
			'component'  => $component,
			'page_title' => $pageTitle,
			'menu_title' => $menuTitle,
			'capability' => $capability,
			'menu_slug'  => $menuSlug,
			'icon_url'   => $iconUrl,
			'position'   => $position
		] );
	}
	
	public function add_submenu_page( $component, $parentSlug, $pageTitle, $menuTitle, $capability, $menuSlug, $position = NULL ){
		array_push( $this->menuSubPages, [
			'component'   => $component,
			'parent_slug' => $parentSlug,
			'page_title'  => $pageTitle,
			'menu_title'  => $menuTitle,
			'capability'  => $capability,
			'menu_slug'   => $menuSlug,
			'position'    => $position,
		] );
	}
	
	private function add( $hooks, $hook, $component, $callback, $priority, $accepted_args ){
		$hooks[] = [
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args
		];
		
		return $hooks;
	}
	
	public function run(){
		foreach( $this->filters as $hook ){
			add_filter( $hook[ 'hook' ], [
				$hook[ 'component' ],
				$hook[ 'callback' ]
			], $hook[ 'priority' ], $hook[ 'accepted_args' ] );
		}
		
		foreach( $this->actions as $hook ){
			add_action( $hook[ 'hook' ], [
				$hook[ 'component' ],
				$hook[ 'callback' ]
			], $hook[ 'priority' ], $hook[ 'accepted_args' ] );
		}
		
		foreach( $this->shortcodes as $shortcode ){
			add_shortcode( $shortcode[ 'hook' ], [ $shortcode[ 'component' ], $shortcode[ 'callback' ] ] );
		}
		
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
	}
	
	public function register_menu(){
		foreach( $this->menuPages as $page ){
			add_menu_page( self::title( $page[ 'page_title' ] ), self::title( $page[ 'menu_title' ] ), $page[ 'capability' ], $page[ 'menu_slug' ],
				[ $page[ 'component' ], 'load_admin_menu' ],
				$page[ 'icon_url' ],
				$page[ 'position' ] );
		}

		foreach( $this->menuSubPages as $subpage ){
			add_submenu_page( $subpage[ 'parent_slug' ], self::title( $subpage[ 'page_title' ] ), self::title( $subpage[ 'menu_title' ] ), $subpage[ 'capability' ], $subpage[ 'menu_slug' ],
				[ $subpage[ 'component' ], 'load_admin_menu' ], $subpage[ 'position' ] );
		}
	}

	/**
	 * Tytuly menu przychodza jako domkniecia z __() i literalem - tlumaczenie dopiero na admin_menu,
	 * gdy domena jest juz wczytana (wczesniejsze __() to notice "called too early" od WP 6.7).
	 */
	private static function title( $title ): string{
		return $title instanceof \Closure ? (string) $title() : (string) $title;
	}
	
}