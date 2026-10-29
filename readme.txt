=== globkurier.pl – Integracja z WooCommerce ===
Contributors: WpOpieka
Donate link: https://wp-opieka.pl/
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.7.1
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Pełna integracja sklepu internetowego WooCommerce z GlobKurier

== Description ==
Pełna integracja sklepu internetowego WooCommerce z GlobKurier

GlobKurier.pl to firma kurierska umożliwiająca wygodne dostarczanie przesyłek. Dzięki korzystaniu z naszego modułu masz zagwarantowane najniższe ceny i najwyższą jakość obsługi. WooCommerce to jedna z najbardziej znanych platform do prowadzenia sklepu internetowego, posiadająca intuicyjny panel administracyjny. Dzięki naszej integracji zaoszczędzisz swój czas i całkowicie zautomatyzujesz proces wysyłki.  Nie musisz sam uzupełniać formalności – wszystkie dane są automatycznie importowane do panelu, z którego będziesz nadawać przesyłki do klientów.  Zobacz wszystkie korzyści, wynikające z integracji GlobKurier.pl i WordPress i rozwijaj z nami swój biznes!

Plugin korzysta z API Google Maps (https://maps.googleapis.com/maps/api/). W związku z tym, aby korzystać z pluginu, musisz posiadać klucz API Google Maps. Klucz API Google Maps jest bezpłatny, ale może wymagać podania danych karty kredytowej. Więcej informacji na temat klucza API Google Maps znajdziesz na stronie https://developers.google.com/maps/gmp-get-started.
Politykę prywatności Google Maps znajdziesz na stronie https://cloud.google.com/maps-platform/terms.

Plugin korzysta z API Inpost (https://api-pl-points.easypack24.net/v1/points) do pobierania punktów paczkomatów.
Politykę prywatności Inpost znajdziesz na stronie https://inpost.pl/polityka-prywatnosci.

Plugin korzysta również z API Globkurier (https://api.globkurier.pl/, https://test.api.globkurier.pl/, https://www.globkurier.pl/shipment-tracking/), jest to wymagane do działania integracji z tym serwisem.
Ich polityka prywatności znajdziesz na stronie https://www.globkurier.pl/polityka-prywatnosci.

Korzyści:
- zaciąganie danych transakcji w sklepie
- automatyczne wprowadzanie numeru listu przewozowego do karty transakcji
- szybkie wysyłanie przesyłek
- wycena on-line kosztu przesyłki, wybór najtańszego spedytora
- automatyczne pobieranie aktualnej oferty
- możliwość drukowania uzupełnionych listów przewozowych
- wyeliminowanie możliwości popełniania błędów przy ręcznym przepisywaniu adresów (np.
 kontrola poprawności kodu pocztowego)

Integracja WooCommerce umożliwi Ci wysyłkę swoich paczek przewoźnikami takimi jak:
- UPS
- DPD
- DHL
- GLS
- TNT
- FedEx
- InPost Paczkomaty
- InPost Kurier
- Meest

== Installation ==
= Automatyczna instalacja =

Automatyczna instalacja jest najłatwiejszą opcją, ponieważ WordPress sam obsługuje przesyłanie plików i nie trzeba opuszczać przeglądarki internetowej. Aby wykonać automatyczną instalację globkurier.pl – Integracja z WooCommerce, zaloguj się do pulpitu nawigacyjnego WordPress, przejdź do menu Wtyczki i kliknij Dodaj nowy.

W polu wyszukiwania wpisz \"globkurier.pl – Integracja z WooCommerce\" i kliknij Wyszukaj wtyczki. Po znalezieniu wtyczki możesz wyświetlić szczegółowe informacje na jej temat, takie jak punkt wydania, ocena i opis. Co najważniejsze, możesz ją zainstalować, klikając \"Zainstaluj teraz\".

= Instalacja ręczna =

Metoda instalacji ręcznej polega na pobraniu naszej wtyczki i przesłaniu jej na serwer za pośrednictwem ulubionej aplikacji FTP. Kodeks WordPress zawiera [instrukcje, jak to zrobić tutaj](http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

= Aktualizacja =

Automatyczne aktualizacje powinny działać idealnie; jak zawsze jednak, upewnij się, że wykonałeś kopię zapasową witryny na wszelki wypadek.

= Konfiguracja =

1. Przejdź do zakładki GlobKurier → Konfiguracja.
2. Uzupełnij dane autoryzacyjne oraz konfiguracyjne: domyślny adres nadania, parametry
przesyłki, obsługa Paczkomatów InPost oraz Paczki w RUCHu.
3. Upewnij się, że masz przygotowanego odpowiedniego przewoźnika w sklepie:
Zakładka WooCommerce → Ustawienia → Wysyłka

== Screenshots ==
1. Wybór paczkomatu z mapy
2. Konfiguracja wtyczki GlobKurier
3. Nadanie przesyłki poprzez wtyczkę GlobKurier

== Changelog ==

2.7.1 - 28.10.2026
	Fix: shipment with dangerous goods declared is blocked in the form with a clear message (GlobKurier does not accept dangerous goods) instead of an API error
	Performance: Protocols page loads shipments page by page (AJAX) instead of the whole history at once
	Performance: shipment history and protocols use an indexed table (filled once in the background, verified before use)
	Performance: admin pages reuse the saved API token instead of logging in to the API on every view; token cleared when login or password changes
	Performance: payment methods and carriers with pickup points cached in settings (12 h, refreshed on save); last known values used when the API is unavailable
	Performance: shorter API timeout for reads (60 s), 300 s kept for shipping and PDF; login retried only on connection or server errors
	Fix: PDF labels and protocols downloaded with the API timeout instead of the WordPress default 5 s
	Fix: shipment history shows the WooCommerce order number instead of the order ID
	Fix: shipment dates and times shown in the store timezone instead of UTC
	Changed: order screen shows shipments right away and loads their status automatically (cached 15 min, "Aktualizuj" refreshes)
	Added: setting to disable the "Wysyłka" column on the order list; the column does no work when hidden in Screen Options
	Changed: unused empty helper posts (globkurier_ghost) created by older versions are removed once in the background; posts holding shipments are kept

2.7 - 27.10.2026
	Added: dangerous goods declaration (containsDangerousGoods) in the shipment form and bulk send
	Security: pickup point search no longer overwrites the shared ORLEN points list
	Security: data from the API and order addresses escaped in the admin panel, maps and shipment history
	Security: nonces and capability checks for crossborder terminals, extra carrier point session and data removal
	Security: public point search limited (known countries only, shorter API timeout, no login retries)
	Security: nonces for PDF label and protocol links
	Security: per-order capability check when shipping, in bulk send and customs import
	Security: addon and required carrier fields escaped in the order panel and bulk send
	Fix: ORLEN Paczka recognised in the order panel (pickup point field, products list)
	Fix: address book - adding a contact no longer reports an error (API 204)
	Fix: extra pickup point carriers - selected point restored after page reload
	Fix: InPost points download (duplicated first page, partial lists), ORLEN points cache expiry
	Fix: checkout validation uses the selected shipping method
	Fix: FedEx pickup points search (fallback to product ID in Poland)
	Fix: ORLEN Paczka - receiver pickup point sent also when the parcel is collected by courier
	Change: API requests via the WordPress HTTP API (sites blocking external requests must allow api.globkurier.pl)
	Change: points cache stored in uploads/globkurier
	Change: checkout block apiVersion 3 (editor in iframe, WordPress 7.0+)
	Change: pickup points list refreshed in the background (WP-Cron) instead of during checkout search
	Deprecated: GlobKurierInpost::getAllPointsGK() - use getAllPointsFromInpost()

2.6 - 06.10.2026
	Fix: fix HPOS mode issues
	Fix: order list issues
	Fix: minor issues

2.5.4.1 - 10.06.2026
	Added: Inpost door to door pickup point support
	Added: Handling disabled addons

2.5.4 - 24.04.2026
	Fixed: Inpost pickup fixes

2.5.1 - 01.04.2026
	Fixed: Load text domain notices
	Added: New logo
	Added: Sticky bagde for return services

2.5.0 - 10.03.2026
	Fixed: Wrong price problem on bulk order page
	Fixed: Double orders problem on validation failed
	Fixed: Fix minor problems

2.4.5 - 11.02.2026
	Fixed: Fix translate problem

2.4.4 - 21.01.2026
	Fixed: Fix namespace problem

2.4.3 - 17.12.2025
	Fixed: Login problem

2.4.2 - 08.12.2025
	Fixed: Minor problems
	Improved: Address fields matching
	Improved: Block checkout compatibility

2.4.1 - 18.09.2025
	Added: Ability to bulk order
	Improve: Search pickup points

2.3.3 - 12.08.2025
	Fixed: Minor issues
	Added: Add option to sum weight for all products in order
	Added: Add products' SKU/EAN to order comment
	Added: Add option to delete all GlobKurier plugin settings

2.3.2 - 12.05.2025
	Fixed addons params

2.3.1 - 12.04.2025
	Improved: remove unnecessary css files

2.3.0 - 02.04.2025
	Fixed: Wrong label for receiver pickup point
	Added: Add crossborder collection type
	Added: Inpost paczkomat international support
	Improved: Add error message when problem with getting products list
	Fixed: Block checkout validation

2.2.2 - 10.01.2025
	Fixed: Fix warnings

2.2.1 - 19.11.2024
	Fixed: Fix PHP 8.3 warnings

2.2.0 - 30.10.2024
	Improved: Globkurier API token refresh mechanism

2.1.9 - 16.10.2024
	Fixed: Fix PHP 8.3 warnings

2.1.8 - 02.10.2024
	Fixed: Fix PHP 8.3 warnings

2.1.7 - 13.09.2024
	Fixed: Remove duplicate pickup times in admin panel, caused by API changes

2.1.6 - 10.09.2024
	Fixed: Notices and critical errors on new order creation with admin panel

2.1.5 - 18.07.2024
	Fixed: Old orders error when HPOS mode is enabled

2.1.4 - 16.07.2024
	Fixed: Order get address when HPOS mode is enabled, in some cases return wrong data

2.1.3 - 20.06.2024
	Fixed: Search Orlen points by id in admin panel

2.1.2
	Fixed: minor fixes

2.1.1
	Fixed: Wrong plugin url when using custom plugin path
	Improved: Disable loading assets when not using pickup points

2.1.0
	Added: Add new carriers pickup point ability
	Added: Add new carriers pickup point map on woocommerce checkout block
	Added: Add new button to get already send packages instead of instant request

2.0
	Added: WooCommerce Block checkout compatibility
	Improved: Inpost points search
	Improved: Orlen points search
	Fixed: missing nonces

1.9
	WooCommerce 8.0 compatibility
	Fix default dimensions

1.7.6
	PHP 8.1 compatibility

1.7.4
	Fix Orlen paczka when map is not enabled
	Fix missing translations

1.7.3
	Orlen public - use cached points for carrierName=orlenPaczka
	Orlen admin - use always live points for specified productId

1.7.0
	Paczka w Ruchu change to Orlen Paczka
	Fix Inpost points downloads
	Inpost points save to json file
	Orlen points save to json file
	Inpost/Orlen points force update when 24hours old
	Ajax search inpost/orlen paczka
	String changes
	Other minor changes

1.5.7
	Fixed tracking url
