<?php

namespace udigroup_globkurier;

class UDIGroup_i18n{
	
	public function load_plugin_textdomain(){
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- tlumaczenia dolaczone do wtyczki (udigroup_globkurier_languages), nie z translate.wordpress.org.
		load_plugin_textdomain( UDIGroup_GLOBKURIER_INIT::getPluginName(), false, dirname( UDIGroup_Helper::getLanguagePath() ) );
	}
}