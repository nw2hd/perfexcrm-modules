<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Decode MIME encoded header text (e.g., =?UTF-8?Q?...?=)
 *
 * @param string $encoded_text
 * @return string
 */
if (!function_exists('decode_mime_header')) {
	function decode_mime_header($encoded_text) {
		if (preg_match('/\=\?UTF\-8\?Q\?(.*?)\?=/i', $encoded_text, $matches)) {
			$decoded = quoted_printable_decode($matches[1]);
			// Replace underscores with spaces
			$decoded = str_replace('_', ' ', $decoded);
			// Convert to UTF-8 if needed
			return mb_convert_encoding($decoded, 'UTF-8', 'auto');
		}
		return $encoded_text; // Return original if no match
	}
}

/*
Module Name: Mailbox
Module URI: https://codecanyon.net/item/mailbox-webmail-client-for-perfex-crm/25308081
Description: Mailbox is a webmail client for Perfex's dashboard.
Version: 2.1.7
Requires at least: 3.0
Author: Themesic Interactive
Author URI: https://1.envato.market/themesic
*/

define('MAILBOX_MODULE', 'mailbox');
define('MAILBOX_MODULE_UPLOAD_FOLDER', module_dir_path(MAILBOX_MODULE, 'uploads'));

// Load Composer autoloader (vendor folder included with module - plug and play)
require_once __DIR__.'/vendor/autoload.php';

/**
 * Fix CSRF setup to wait for jQuery - MUST run in head before any scripts
 * This function MUST be defined BEFORE hooks are registered
 *
 * @return null
 */
function mailbox_fix_csrf_setup()
{
    try {
        if (!function_exists('get_option')) {
            return; // get_option not available yet
        }

        $enabled = get_option('mailbox_enabled');
        if ('1' == $enabled) {
            echo '<script type="text/javascript">
// CRITICAL: Override csrf_jquery_ajax_setup IMMEDIATELY in head
// This must execute before init_tail() outputs scripts that call it
// Store original function if it exists
var originalCsrfSetup = window.csrf_jquery_ajax_setup;

window.csrf_jquery_ajax_setup = function() {
    // Call original Perfex CRM CSRF setup first if it exists
    if (typeof originalCsrfSetup === "function") {
        try {
            originalCsrfSetup();
        } catch(e) {
            console.warn("Original CSRF setup failed:", e);
        }
    }

    function performCsrfSetup() {
        if (typeof jQuery !== "undefined" && typeof jQuery.ajaxSetup !== "undefined") {
            try {
                // Try multiple methods to find CSRF token
                var csrfToken = null;
                var csrfTokenName = "csrf_token_name";

                // Method 1: Meta tag
                var csrfMeta = document.querySelector("meta[name=\"csrf-token\"]");
                if (csrfMeta) {
                    csrfToken = csrfMeta.getAttribute("content");
                }

                // Method 2: Form input
                if (!csrfToken) {
                    var csrfInput = document.querySelector("input[name=\"" + csrfTokenName + "\"]");
                    if (csrfInput) {
                        csrfToken = csrfInput.value;
                    }
                }

                // Method 3: Any input starting with csrf
                if (!csrfToken) {
                    var anyCsrfInput = document.querySelector("input[name^=\"csrf\"]");
                    if (anyCsrfInput) {
                        csrfToken = anyCsrfInput.value;
                        csrfTokenName = anyCsrfInput.name;
                    }
                }

                // Method 4: Check existing ajaxSetup and chain it
                var existingBeforeSend = jQuery.ajaxSetup().beforeSend;

                if (csrfToken) {
                    jQuery.ajaxSetup({
                        beforeSend: function(xhr, settings) {
                            // Call existing beforeSend if it exists (from Perfex CRM)
                            if (typeof existingBeforeSend === "function") {
                                existingBeforeSend.call(this, xhr, settings);
                            }
                            // Add CSRF token header
                            xhr.setRequestHeader("X-CSRF-TOKEN", csrfToken);
                            // Also add to POST data
                            if (settings && settings.data) {
                                if (typeof settings.data === "string") {
                                    settings.data += "&" + csrfTokenName + "=" + encodeURIComponent(csrfToken);
                                } else if (typeof settings.data === "object") {
                                    settings.data[csrfTokenName] = csrfToken;
                                }
                            }
                        }
                    });
                } else if (typeof existingBeforeSend === "function") {
                    // Even if we do not find token, ensure existing setup is preserved
                    jQuery.ajaxSetup({
                        beforeSend: existingBeforeSend
                    });
                }
            } catch(e) {
                console.error("Mailbox CSRF Setup Error:", e);
            }
        }
    }

    if (typeof jQuery !== "undefined" && typeof jQuery.ajaxSetup !== "undefined") {
        performCsrfSetup();
    } else {
        var attempts = 0;
        var maxAttempts = 100;
        function waitForJQuery() {
            attempts++;
            if (typeof jQuery !== "undefined" && typeof jQuery.ajaxSetup !== "undefined") {
                performCsrfSetup();
            } else if (attempts < maxAttempts) {
                setTimeout(waitForJQuery, 100);
            } else {
                console.error("Mailbox: jQuery failed to load after " + (maxAttempts * 100) + "ms");
            }
        }
        setTimeout(waitForJQuery, 50);
    }
};
</script>';
        }
    } catch (Exception $e) {
        // Silently fail - do not break the page if CSRF setup fails
        if (function_exists('log_activity')) {
            log_activity('Mailbox CSRF setup error: ' . $e->getMessage(), null);
        }
    } catch (Error $e) {
        // Silently fail - do not break the page if CSRF setup fails
        if (function_exists('log_activity')) {
            log_activity('Mailbox CSRF setup fatal error: ' . $e->getMessage(), null);
        }
    }
}

hooks()->add_action('after_cron_run', 'scan_email_server');
hooks()->add_action('after_cron_run', 'send_schedule_emails');
hooks()->add_action('app_admin_head', 'mailbox_fix_csrf_setup');
hooks()->add_action('app_admin_head', 'mailbox_add_head_components');
hooks()->add_action('app_admin_footer', 'mailbox_load_js');
hooks()->add_action('admin_init', 'mailbox_module_init_menu_items');
hooks()->add_filter('migration_tables_to_replace_old_links', 'mailbox_migration_tables_to_replace_old_links');
hooks()->add_action('after_lead_lead_tabs', 'mailbox_after_lead_lead_tabs');
hooks()->add_action('after_lead_tabs_content', 'mailbox_after_lead_tabs_content');
hooks()->add_action('customer_profile_tabs', 'customer_profile_tabs');

/**
 * Injects chat CSS.
 *
 * @return null
 */
function mailbox_add_head_components()
{
    if ('1' == get_option('mailbox_enabled')) {
        $CI = &get_instance();
        echo '<link href="'.base_url('modules/mailbox/assets/css/mailbox_styles.css').'?v='.$CI->app_scripts->core_version().'"  rel="stylesheet" type="text/css" />';

        // Add bodyclass for settings page if we're on mailbox_settings group
        if ($CI->router->fetch_class() === 'settings' && $CI->input->get('group') === 'mailbox_settings') {
            echo '<script>document.body.classList.add("mailbox", "mailbox-settings");</script>';
        }
    }
}

/**
 * Injects chat Javascript.
 *
 * @return null
 */
function mailbox_load_js()
{
    // Skip inline scripts if we're on standalone pages (rules, etc.)
    // Check multiple ways to detect rules page

    // Method 0: Check if we're in rules context (most aggressive)
    $CI = &get_instance();
    $class = $CI->router->fetch_class();
    $method = $CI->router->fetch_method();
    if ($class === 'mailbox' && ($method === 'rules_index' || $method === 'rules_table')) {
        return;
    }

    // Method 1: Check constant
    if (defined('SKIP_MAILBOX_INLINE_SCRIPTS') && SKIP_MAILBOX_INLINE_SCRIPTS) {
        return;
    }

    // Method 2: Check SERVER variable
    if (isset($_SERVER['__SKIP_MAILBOX_INLINE_SCRIPTS'])) {
        return;
    }

    // Method 3: Check GET parameter
    if (isset($_GET['__skip_mailbox_inline_scripts'])) {
        return;
    }

    // Method 4: Check current URI
    $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    if (strpos($uri, 'mailbox/rules_') !== false) {
        return;
    }

    if ('1' == get_option('mailbox_enabled')) {

        // CRITICAL: Add immediate script to prevent/prevent wrong table initialization
        // This runs before document.ready to catch any auto-initialization
        echo '<script type="text/javascript">
        (function() {
            // Immediately destroy preview table if it was auto-initialized (should be manual init only)
            if (typeof jQuery !== "undefined" && jQuery.fn.DataTable && jQuery.fn.DataTable.isDataTable(".table-mailbox-preview")) {
                try {
                    jQuery(".table-mailbox-preview").DataTable().clear().destroy(true);
                    jQuery(".table-mailbox-preview").find("tbody").empty();
                } catch(e) {
                    // Ignore errors during cleanup
                }
            }
        })();
        </script>';
        echo '<script src="'.module_dir_url('mailbox', 'assets/js/mailbox_js.js').'?v='.$CI->app_scripts->core_version().'"></script>';
        echo '<script src="'.module_dir_url('mailbox', 'assets/js/mail_actions_click.js').'?v='.$CI->app_scripts->core_version().'"></script>';

        // Add inline mailbox-specific scripts after all Perfex scripts are loaded
        $group = !$CI->input->get('group') ? 'inbox' : $CI->input->get('group');
        echo '<script type="text/javascript">
        "use strict";

        // Wait for jQuery and all Perfex scripts to be fully loaded
        (function() {
            function initMailboxInlineScripts() {
                if (typeof jQuery === "undefined" || typeof $ === "undefined") {
                    setTimeout(initMailboxInlineScripts, 50);
                    return;
                }

                var mailbox_group = "'.$group.'";

                // Cookie functions (don\'t require jQuery)
                function getCookie(name) {
                    var value = "; " + document.cookie;
                    var parts = value.split("; " + name + "=");
                    if (parts.length == 2) return parts.pop().split(";").shift();
                    return null;
                }

                function setCookie(name, value, days) {
                    var date = new Date();
                    date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                    var expires = "; expires=" + date.toUTCString();
                    document.cookie = name + "=" + (value || "") + expires + "; path=/";
                }

                var preview_pane_enabled = getCookie("mailbox_preview_pane") == "1";

                // Preview Pane Functions
                window.togglePreviewPane = function() {
                    preview_pane_enabled = !preview_pane_enabled;
                    setCookie("mailbox_preview_pane", preview_pane_enabled ? "1" : "0", 365);

                    if (preview_pane_enabled) {
                        showPreviewPane();
                    } else {
                        hidePreviewPane();
                    }
                };

                window.showPreviewPane = function() {
                    // Debug log removed

                    jQuery("#mailbox_split_view").show();
                    jQuery("#mailbox_full_table").hide();
                    jQuery("#preview_pane_text").text("'.addslashes(_l('mailbox_hide_preview')).'");
                    jQuery("#toggle_preview_pane i").removeClass("fa-columns").addClass("fa-window-close");

                    // Update column 4 label to "Date" for preview pane
                    var column4Item = jQuery(\'#column_visibility_menu a[data-column="4"]\');
                    if (column4Item.length && column4Item.data(\'preview-label\')) {
                        column4Item.find(\'.column-label\').text(column4Item.data(\'preview-label\'));
                    }

                    // Hide columns that don\'t exist in preview pane (5=Assigned Customers, 6=Date or Scheduled At, 7=Date for sent or draft)
                    jQuery(\'#column_visibility_menu a[data-column="5"]\').parent().hide();
                    jQuery(\'#column_visibility_menu a[data-column="6"]\').parent().hide();
                    jQuery(\'#column_visibility_menu a[data-column="7"]\').parent().hide();

                    // Use container-aware selector - search within the split view container
                    var previewTable = jQuery("#mailbox_split_view").find("table.table-mailbox-preview, table[data-last-order-identifier=\"mailbox-preview\"]");
                    if (previewTable.length === 0) {
                        // Fallback: try direct selector
                        previewTable = jQuery(".table-mailbox-preview");
                    }
                    // Debug log removed
                    // Debug log removed

                    if (previewTable.length === 0) {
                        console.error("[Preview Pane] ERROR: Preview table element not found!");
                        // Try to find any table in the split view
                        var anyTable = jQuery("#mailbox_split_view").find("table");
                        // Debug log removed
                        return;
                    }

                    // Check if DataTable already exists on this specific element
                    var tableExists = previewTable.length > 0 && jQuery.fn.DataTable && jQuery.fn.DataTable.isDataTable(previewTable);
                    // Debug log removed

                    if (tableExists) {
                        try {
                            var existingTable = previewTable.DataTable();
                            var currentUrl = existingTable.settings()[0].ajax ? existingTable.settings()[0].ajax.url : null;
                            // Debug log removed

                            var expectedUrl = admin_url + "mailbox/table_preview/'.$group.'";
                            // Debug log removed

                            // If URL matches, just reload the data
                            if (currentUrl && currentUrl.toString().indexOf("table_preview") !== -1) {
                                // Debug log removed
                                existingTable.clear();
                                existingTable.ajax.reload(null, false);
                                return;
                            } else {
                                // Debug log removed
                                existingTable.clear().destroy(false); // Do not remove from DOM
                                // Clear tbody but preserve thead
                                if (previewTable.find("tbody").length) {
                                    previewTable.find("tbody").empty();
                                } else {
                                    previewTable.append("<tbody></tbody>");
                                }
                            }
                        } catch(e) {
                            console.error("[Preview Pane] Error destroying existing table:", e);
                            try {
                                if (jQuery.fn.DataTable.isDataTable(previewTable)) {
                                    previewTable.DataTable().destroy(false); // Do not remove from DOM
                                }
                            } catch(e2) {
                                console.error("[Preview Pane] Error in destroy:", e2);
                            }
                            if (previewTable.find("tbody").length) {
                                previewTable.find("tbody").empty();
                            } else {
                                previewTable.append("<tbody></tbody>");
                            }
                        }
                    } else {
                        // No DataTable exists, just ensure tbody exists and is empty
                        if (!previewTable.find("tbody").length) {
                            previewTable.append("<tbody></tbody>");
                        } else {
                            previewTable.find("tbody").empty();
                        }
                    }

                    // Destroy full table if it exists (but do not remove from DOM)
                    var fullTableForDestroy = jQuery("#mailbox_full_table").find("table.table-mailbox, table[data-last-order-identifier=\"mailbox\"]");
                    if (fullTableForDestroy.length === 0) {
                        fullTableForDestroy = jQuery(".table-mailbox");
                    }
                    if (fullTableForDestroy.length > 0 && jQuery.fn.DataTable && jQuery.fn.DataTable.isDataTable(fullTableForDestroy)) {
                        // Debug log removed
                        fullTableForDestroy.DataTable().clear().destroy(false); // Do not remove from DOM
                    }

                    // Wait a moment, then initialize preview table with correct endpoint
                    setTimeout(function() {
                        // Re-find the table element (might have changed)
                        previewTable = jQuery("#mailbox_split_view").find("table.table-mailbox-preview, table[data-last-order-identifier=\"mailbox-preview\"]");
                        if (previewTable.length === 0) {
                            previewTable = jQuery(".table-mailbox-preview");
                        }

                        // Double-check DataTable is destroyed and tbody is empty
                        if (jQuery.fn.DataTable && jQuery.fn.DataTable.isDataTable(previewTable)) {
                            previewTable.DataTable().clear().destroy(false);
                            previewTable.find("tbody").empty();
                        }

                        if (previewTable.length > 0) {
                            // Debug log removed
                            var webmailTableNotSortable = [0];
                            // Sort by column 0 (ID) DESC to match PHP sorting - higher ID = newer email
                            // This ensures preview pane shows newest emails first, matching the full table
                            // Build URL with current query parameters (including search_tag)
                            var previewUrl = admin_url + "mailbox/table_preview/'.$group.'";
                            var urlParams = new URLSearchParams(window.location.search);
                            if (urlParams.toString()) {
                                previewUrl += "?" + urlParams.toString();
                            }
                            // Initialize DataTable with pagination enabled
                            initDataTable(previewTable, previewUrl, "undefined", webmailTableNotSortable, "undefined", [0, "desc"]);

                            // Ensure pagination is enabled immediately after initialization
                            setTimeout(function() {
                                if (jQuery.fn.DataTable.isDataTable(previewTable)) {
                                    var dt = previewTable.DataTable();
                                    console.log("[Pagination Debug Preview] Initial state:", {
                                        paging: dt.settings()[0].oInit.paging,
                                        pageLength: dt.settings()[0].oInit.pageLength,
                                        bPaginate: dt.settings()[0].oFeatures.bPaginate,
                                        _iDisplayLength: dt.settings()[0]._iDisplayLength,
                                        pageInfo: dt.page.info()
                                    });

                                    // Force enable pagination with default 10 per page
                                    dt.settings()[0].oInit.paging = true;
                                    dt.settings()[0].oFeatures.bPaginate = true;
                                    // Set default to 10 if not already set by user
                                    if (!dt.settings()[0].oInit.pageLength || dt.settings()[0].oInit.pageLength === 25) {
                                        dt.settings()[0].oInit.pageLength = 10;
                                        dt.settings()[0]._iDisplayLength = 10;
                                        dt.page.len(10);
                                    }
                                    dt.draw();

                                    // Add event handler for length change to ensure server-side redraw
                                    previewTable.off(\'length.dt\').on(\'length.dt\', function(e, settings, len) {
                                        console.log("[Pagination Debug Preview] ===== LENGTH CHANGE EVENT =====");
                                        console.log("[Pagination Debug Preview] New length:", len);
                                        var dtInstance = previewTable.DataTable();
                                        var pageInfoBefore = dtInstance.page.info();
                                        console.log("[Pagination Debug Preview] Page info BEFORE draw:", JSON.stringify(pageInfoBefore, null, 2));
                                        console.log("[Pagination Debug Preview] Current page length:", dtInstance.page.len());
                                        console.log("[Pagination Debug Preview] Settings length:", dtInstance.settings()[0]._iDisplayLength);

                                            // Also listen to the xhr event to see what the server returns
                                            previewTable.off(\'xhr.dt\').on(\'xhr.dt\', function(e, settings, json, xhr) {
                                                console.log("[Pagination Debug Preview] ===== AJAX RESPONSE =====");
                                                if (json === null || json === undefined) {
                                                    console.error("[Pagination Debug Preview] AJAX response is NULL! Check Network tab for error details.");
                                                    console.log("[Pagination Debug Preview] XHR status:", xhr ? xhr.status : "unknown");
                                                    console.log("[Pagination Debug Preview] XHR responseText:", xhr ? xhr.responseText : "unknown");
                                                    return;
                                                }
                                                console.log("[Pagination Debug Preview] Response data:", json);
                                                // DataTables uses aaData property, not data property
                                                var recordsReturned = json[\'aaData\'] ? json[\'aaData\'].length : (json[\'data\'] ? json[\'data\'].length : 0);
                                                console.log("[Pagination Debug Preview] Records returned:", recordsReturned);
                                                console.log("[Pagination Debug Preview] Total records:", json.iTotalRecords);
                                                console.log("[Pagination Debug Preview] Display records:", json.iTotalDisplayRecords);
                                            });

                                            // Also listen for AJAX errors
                                            previewTable.off(\'error.dt\').on(\'error.dt\', function(e, settings, techNote, message) {
                                                console.error("[Pagination Debug Preview] ===== AJAX ERROR =====");
                                                console.error("[Pagination Debug Preview] Error message:", message);
                                                console.error("[Pagination Debug Preview] Technical note:", techNote);
                                            });

                                        dtInstance.draw();

                                        setTimeout(function() {
                                            var pageInfoAfter = dtInstance.page.info();
                                            console.log("[Pagination Debug Preview] Page info AFTER draw:", JSON.stringify(pageInfoAfter, null, 2));
                                            console.log("[Pagination Debug Preview] Visible rows:", previewTable.find(\'tbody tr\').length);
                                        }, 500);
                                    });

                                    console.log("[Pagination Debug Preview] After fix:", dt.page.info());
                                }
                            }, 300);
                        } else {
                            // Debug log removed
                        }
                    }, 100);
                };

                window.hidePreviewPane = function() {
                    // Debug log removed

                    jQuery("#mailbox_split_view").hide();
                    jQuery("#mailbox_full_table").show();
                    jQuery("#preview_pane_text").text("'.addslashes(_l('mailbox_show_preview')).'");
                    jQuery("#toggle_preview_pane i").removeClass("fa-window-close").addClass("fa-columns");
                    jQuery("#preview_content").html("<p class=\"text-muted text-center\">'.addslashes(_l('mailbox_select_email_to_preview')).'</p>");

                    // Update column 4 label back to "Body" for main table
                    var column4Item = jQuery(\'#column_visibility_menu a[data-column="4"]\');
                    if (column4Item.length && column4Item.data(\'main-label\')) {
                        column4Item.find(\'.column-label\').text(column4Item.data(\'main-label\'));
                    }

                    // Show columns that exist in main table (5=Assigned Customers, 6=Date or Scheduled At, 7=Date for sent or draft)
                    jQuery(\'#column_visibility_menu a[data-column="5"]\').parent().show();
                    jQuery(\'#column_visibility_menu a[data-column="6"]\').parent().show();
                    jQuery(\'#column_visibility_menu a[data-column="7"]\').parent().show();

                    // Destroy preview table (but do not remove from DOM)
                    var previewTableForDestroy = jQuery("#mailbox_split_view").find("table.table-mailbox-preview, table[data-last-order-identifier=\"mailbox-preview\"]");
                    if (previewTableForDestroy.length === 0) {
                        previewTableForDestroy = jQuery(".table-mailbox-preview");
                    }
                    if (previewTableForDestroy.length > 0 && jQuery.fn.DataTable && jQuery.fn.DataTable.isDataTable(previewTableForDestroy)) {
                        // Debug log removed
                        try {
                            previewTableForDestroy.DataTable().clear().destroy(false); // Do not remove from DOM
                        } catch(e) {
                            console.error("[Preview Pane] Error destroying preview table:", e);
                        }
                    }

                    // Check if full table already exists - use container-aware selector
                    var fullTable = jQuery("#mailbox_full_table").find("table.table-mailbox, table[data-last-order-identifier=\"mailbox\"]");
                    if (fullTable.length === 0) {
                        // Fallback: try direct selector
                        fullTable = jQuery(".table-mailbox");
                    }
                    var fullTableExists = fullTable.length > 0 && jQuery.fn.DataTable && jQuery.fn.DataTable.isDataTable(fullTable);
                    // Debug log removed
                    // Debug log removed
                    if (fullTable.length === 0) {
                        console.error("[Preview Pane] ERROR: Full table element not found in #mailbox_full_table!");
                        var anyTable = jQuery("#mailbox_full_table").find("table");
                        // Debug log removed
                    }

                    if (fullTableExists) {
                        try {
                            var existingFullTable = fullTable.DataTable();
                            var currentUrl = existingFullTable.settings()[0].ajax ? existingFullTable.settings()[0].ajax.url : null;
                            // Debug log removed

                            var expectedUrl = admin_url + "mailbox/table/'.$group.'";
                            // Debug log removed

                            // If URL matches, just reload
                            if (currentUrl && currentUrl.toString().indexOf("mailbox/table/") !== -1 && currentUrl.toString().indexOf("table_preview") === -1) {
                                // Debug log removed
                                existingFullTable.clear();
                                existingFullTable.ajax.reload(null, false);
                                return;
                            } else {
                                // Debug log removed
                                existingFullTable.clear().destroy(false); // Do not remove from DOM
                                if (fullTable.find("tbody").length) {
                                    fullTable.find("tbody").empty();
                                } else {
                                    fullTable.append("<tbody></tbody>");
                                }
                            }
                        } catch(e) {
                            console.error("[Preview Pane] Error with existing full table:", e);
                            try {
                                if (jQuery.fn.DataTable.isDataTable(fullTable)) {
                                    fullTable.DataTable().destroy(false); // Do not remove from DOM
                                }
                            } catch(e2) {
                                console.error("[Preview Pane] Error destroying full table:", e2);
                            }
                            if (fullTable.find("tbody").length) {
                                fullTable.find("tbody").empty();
                            } else {
                                fullTable.append("<tbody></tbody>");
                            }
                        }
                    }

                    // Wait a bit, then reinitialize full table
                    setTimeout(function() {
                        // Re-find the table element (might have changed)
                        fullTable = jQuery("#mailbox_full_table").find("table.table-mailbox, table[data-last-order-identifier=\"mailbox\"]");
                        if (fullTable.length === 0) {
                            fullTable = jQuery(".table-mailbox");
                        }

                        if (fullTable.length) {
                            // Ensure tbody exists and is empty
                            if (!fullTable.find("tbody").length) {
                                fullTable.append("<tbody></tbody>");
                            } else {
                                fullTable.find("tbody").empty();
                            }

                            // Check if still not initialized
                            if (!jQuery.fn.DataTable.isDataTable(fullTable)) {
                                // Debug log removed
                                var webmailTableNotSortable = [0];
                                // Build URL with current query parameters (including search_tag)
                                var fullTableUrl = admin_url + "mailbox/table/'.$group.'";
                                var urlParams = new URLSearchParams(window.location.search);
                                if (urlParams.toString()) {
                                    fullTableUrl += "?" + urlParams.toString();
                                }
                                // Initialize DataTable with pagination enabled
                                initDataTable(fullTable, fullTableUrl, "undefined", webmailTableNotSortable, "undefined", [6, "desc"]);

                                // Ensure pagination is enabled immediately after initialization
                                setTimeout(function() {
                                    if (jQuery.fn.DataTable.isDataTable(fullTable)) {
                                        var dt = fullTable.DataTable();
                                        console.log("[Pagination Debug Full] Initial state:", {
                                            paging: dt.settings()[0].oInit.paging,
                                            pageLength: dt.settings()[0].oInit.pageLength,
                                            bPaginate: dt.settings()[0].oFeatures.bPaginate,
                                            _iDisplayLength: dt.settings()[0]._iDisplayLength,
                                            pageInfo: dt.page.info()
                                        });

                                        // Force enable pagination with default 10 per page
                                        dt.settings()[0].oInit.paging = true;
                                        dt.settings()[0].oFeatures.bPaginate = true;
                                        // Set default to 10 if not already set by user
                                        if (!dt.settings()[0].oInit.pageLength || dt.settings()[0].oInit.pageLength === 25) {
                                            dt.settings()[0].oInit.pageLength = 10;
                                            dt.settings()[0]._iDisplayLength = 10;
                                            dt.page.len(10);
                                        }
                                        dt.draw(); // Force server-side redraw

                                        // Add event handler for length change to ensure server-side redraw
                                        fullTable.off(\'length.dt\').on(\'length.dt\', function(e, settings, len) {
                                            console.log("[Pagination Debug Full] ===== LENGTH CHANGE EVENT =====");
                                            console.log("[Pagination Debug Full] New length:", len);
                                            var dtInstance = fullTable.DataTable();
                                            var pageInfoBefore = dtInstance.page.info();
                                            console.log("[Pagination Debug Full] Page info BEFORE draw:", JSON.stringify(pageInfoBefore, null, 2));
                                            console.log("[Pagination Debug Full] Current page length:", dtInstance.page.len());
                                            console.log("[Pagination Debug Full] Settings length:", dtInstance.settings()[0]._iDisplayLength);

                                            // Also listen to the xhr event to see what the server returns
                                            fullTable.off(\'xhr.dt\').on(\'xhr.dt\', function(e, settings, json, xhr) {
                                                console.log("[Pagination Debug Full] ===== AJAX RESPONSE =====");
                                                if (json === null || json === undefined) {
                                                    console.error("[Pagination Debug Full] AJAX response is NULL! Check Network tab for error details.");
                                                    console.log("[Pagination Debug Full] XHR status:", xhr ? xhr.status : "unknown");
                                                    console.log("[Pagination Debug Full] XHR responseText:", xhr ? xhr.responseText : "unknown");
                                                    return;
                                                }
                                                console.log("[Pagination Debug Full] Response data:", json);
                                                // DataTables uses aaData property, not data property
                                                var recordsReturned = json[\'aaData\'] ? json[\'aaData\'].length : (json[\'data\'] ? json[\'data\'].length : 0);
                                                console.log("[Pagination Debug Full] Records returned:", recordsReturned);
                                                console.log("[Pagination Debug Full] Total records:", json.iTotalRecords);
                                                console.log("[Pagination Debug Full] Display records:", json.iTotalDisplayRecords);
                                            });

                                            // Also listen for AJAX errors
                                            fullTable.off(\'error.dt\').on(\'error.dt\', function(e, settings, techNote, message) {
                                                console.error("[Pagination Debug Full] ===== AJAX ERROR =====");
                                                console.error("[Pagination Debug Full] Error message:", message);
                                                console.error("[Pagination Debug Full] Technical note:", techNote);
                                            });

                                            dtInstance.draw();

                                            setTimeout(function() {
                                                var pageInfoAfter = dtInstance.page.info();
                                                console.log("[Pagination Debug Full] Page info AFTER draw:", JSON.stringify(pageInfoAfter, null, 2));
                                                console.log("[Pagination Debug Full] Visible rows:", fullTable.find(\'tbody tr\').length);
                                            }, 500);
                                        });

                                        console.log("[Pagination Debug Full] After fix:", dt.page.info());
                                    }
                                }, 300);
                            } else {
                                // Debug log removed
                            }
                        } else {
                            console.error("[Preview Pane] ERROR: Full table element not found!");
                        }
                    }, 100);
                };

                window.closePreviewPane = function() {
                    togglePreviewPane();
                };

                window.loadEmailPreview = function(email_id) {
                    if (!email_id) {
                        jQuery("#preview_content").html("<p class=\"text-muted text-center\">'.addslashes(_l('mailbox_select_email_to_preview')).'</p>");
                        return;
                    }

                    jQuery("#preview_content").html("<div class=\"text-center\"><i class=\"fa fa-spinner fa-spin fa-2x\"></i><p>'.addslashes(_l('mailbox_loading')).'</p></div>");

                    jQuery.ajax({
                        url: admin_url + "mailbox/preview/" + email_id,
                        type: "GET",
                        dataType: "html",
                        success: function(response) {
                            if (response && response.trim().length > 0) {
                                jQuery("#preview_content").html(response);
                                jQuery("#preview_content").data("email-id", email_id);

                                // REMOVED: Old auto-mark code - now handled by the main preview code below

                                // Re-initialize Bootstrap Select for tag dropdown in preview pane
                                // Use longer timeout to ensure DOM is fully ready
                                setTimeout(function() {
                                    // First, try to initialize all selectpickers in preview content using Perfex CRM init function
                                    if (typeof init_selectpicker === "function") {
                                        init_selectpicker();
                                    }

                                    // Try multiple selectors to find the tag select element
                                    var $tagSelect = jQuery("#preview_content").find("select[name^=\'mail_tag_select\'], select.mail-tag-multiple, select[data-id][data-type]");
                                    console.log("Looking for tag select in preview:", $tagSelect.length, "selectpicker available:", typeof jQuery.fn.selectpicker !== "undefined");

                                    if ($tagSelect.length > 0) {
                                        // Ensure selectpicker is available
                                        if (typeof jQuery.fn.selectpicker === "undefined") {
                                            console.error("Bootstrap Select plugin not loaded!");
                                            return;
                                        }

                                        // Remove disabled attribute if present
                                        $tagSelect.prop("disabled", false);

                                        // Destroy existing Bootstrap Select if it exists
                                        try {
                                            if ($tagSelect.hasClass("selectpicker") || $tagSelect.next(".bootstrap-select").length > 0) {
                                                $tagSelect.selectpicker("destroy");
                                            }
                                        } catch(e) {
                                            console.warn("Error destroying Bootstrap Select (may not exist):", e);
                                        }

                                        // Wait a bit for destroy to complete, then initialize
                                        setTimeout(function() {
                                            try {
                                                // Ensure select has selectpicker class
                                                if (!$tagSelect.hasClass("selectpicker")) {
                                                    $tagSelect.addClass("selectpicker");
                                                }

                                                // Remove disabled attribute again before initialization
                                                $tagSelect.prop("disabled", false);

                                                $tagSelect.selectpicker({
                                                    noneSelectedText: "'.addslashes(_l('mailbox_tags')).'",
                                                    actionsBox: true,
                                                    multipleSeparator: ", ",
                                                    selectedTextFormat: "count > 2",
                                                    width: "100%",
                                                    size: false,
                                                    liveSearch: true
                                                });
                                                $tagSelect.selectpicker("refresh");
                                                console.log("Bootstrap Select initialized successfully in preview pane");
                                            } catch(e) {
                                                console.error("Error initializing Bootstrap Select:", e);
                                            }
                                        }, 100);
                                    } else {
                                        console.warn("Tag select not found in preview content");
                                    }

                                    // Re-initialize tooltips in preview content
                                    if (typeof init_btn_with_tooltips === "function") {
                                        init_btn_with_tooltips();
                                    } else if (jQuery.fn.tooltip) {
                                        jQuery("#preview_content [data-toggle=\"tooltip\"]").tooltip();
                                    }

                                    // Re-register tag update handlers for the newly loaded select
                                    // The handlers are already registered on body, but ensure they work
                                    var $previewTagSelect = jQuery("#preview_content").find("select[name^=\'mail_tag_select\'], select[data-id][data-type]");
                                    if ($previewTagSelect.length > 0) {
                                        console.log("Found tag select in preview pane, handlers should work via body delegation");
                                    }
                                }, 500);

                                // Mark email as read when previewed (only if setting is enabled)
                                // Use the global variable set in document ready, default to "0" (disabled) if not set
                                var autoMarkRead = typeof mailbox_auto_mark_read_on_preview !== "undefined" ? mailbox_auto_mark_read_on_preview : "0";
                                if (autoMarkRead === "1" || autoMarkRead === 1) {
                                    // Get CSRF token from meta tag or form
                                    var csrfToken = jQuery("meta[name=\"csrf-token\"]").attr("content");
                                    var csrfTokenName = jQuery("meta[name=\"csrf-token-name\"]").attr("content") || "csrf_token_name";

                                    var postData = {
                                        group: "'.$group.'",
                                        action: "read",
                                        value: 1,
                                        id: email_id,
                                        type: "inbox"
                                    };

                                    // Add CSRF token if available
                                    if (csrfToken) {
                                        postData[csrfTokenName] = csrfToken;
                                    }

                                    jQuery.post(admin_url + "mailbox/update_field", postData).fail(function(xhr) {
                                        if (xhr.status === 419) {
                                            console.warn("CSRF token expired, retrying with fresh token");
                                            // Try to get fresh token and retry
                                            var freshToken = jQuery("meta[name=\"csrf-token\"]").attr("content");
                                            if (freshToken) {
                                                postData[csrfTokenName] = freshToken;
                                                jQuery.post(admin_url + "mailbox/update_field", postData);
                                            }
                                        }
                                    });
                                }

                                // Update table row styling
                                jQuery(".table-mailbox tbody tr").removeClass("selected");
                                jQuery(".table-mailbox tbody tr a[data-email-id=\"" + email_id + "\"]").closest("tr").addClass("selected");
                            } else {
                                jQuery("#preview_content").html("<p class=\"text-danger\">'.addslashes(_l('mailbox_preview_error')).'</p>");
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error("Preview error:", status, error);
                            jQuery("#preview_content").html("<p class=\"text-danger\">'.addslashes(_l('mailbox_preview_error')).'<br><small>" + error + "</small></p>");
                        }
                    });
                };

                // Get settings with defaults for smooth behavior
                ';

        // Get per-staff setting for auto-mark as read (PHP code outside echo)
        $CI = &get_instance();
        $CI->load->model('staff_model');
        $staff_settings = $CI->staff_model->get(get_staff_user_id());
        $auto_mark_read = isset($staff_settings->mailbox_auto_mark_read_on_preview)
            ? $staff_settings->mailbox_auto_mark_read_on_preview
            : get_option('mailbox_auto_mark_read_on_preview');

        echo '
                // Settings from PHP (per-staff with global fallback)
                var auto_mark_read_on_preview = "'.$auto_mark_read.'";
                var keyboard_shortcuts_enabled = '.(get_option('mailbox_keyboard_shortcuts_enabled') ?: '1').';
                var archive_enabled = '.(get_option('mailbox_archive_enabled') ?: '1').';
                var snooze_enabled = '.(get_option('mailbox_snooze_enabled') ?: '1').';

                // Make settings available globally
                window.mailbox_keyboard_shortcuts_enabled = keyboard_shortcuts_enabled;
                window.mailbox_archive_enabled = archive_enabled;
                window.mailbox_snooze_enabled = snooze_enabled;
                window.mailbox_auto_mark_read_on_preview = auto_mark_read_on_preview;

                // Initialize when document is ready
                jQuery(document).ready(function($) {
                    // Position mail-actions-popup dynamically to prevent cutoff
                    function positionMailActionsPopup() {
                        jQuery(document).on(\'mouseenter\', \'.mail-actions-dropdown\', function() {
                            var $dropdown = jQuery(this);
                            var $popup = $dropdown.find(\'.mail-actions-popup\');
                            var $trigger = $dropdown.find(\'.mail-actions-trigger\');

                            if ($popup.length && $trigger.length) {
                                // Use setTimeout to allow CSS hover to show popup first
                                setTimeout(function() {
                                    var triggerOffset = $trigger.offset();
                                    var triggerHeight = $trigger.outerHeight();
                                    var popupHeight = $popup.outerHeight() || 100;
                                    var windowHeight = jQuery(window).height();
                                    var scrollTop = jQuery(window).scrollTop();

                                    // Calculate if popup would be cut off at bottom
                                    var spaceBelow = windowHeight - (triggerOffset.top - scrollTop + triggerHeight);
                                    var spaceAbove = triggerOffset.top - scrollTop;

                                    // If not enough space below but enough space above, show above
                                    if (spaceBelow < popupHeight + 10 && spaceAbove > popupHeight + 10) {
                                        $popup.addClass(\'show-above\');
                                    } else {
                                        $popup.removeClass(\'show-above\');
                                    }
                                }, 50);
                            }
                        });
                    }

                    positionMailActionsPopup();
                    // Initialize preview pane state (default: disabled)
                    var default_preview_pane = '.(get_option('mailbox_default_preview_pane') ?: '0').';

                    // CRITICAL: Prevent auto-initialization of preview table
                    // Destroy it immediately if it was auto-initialized (should be manual init only)
                    if (jQuery.fn.DataTable && jQuery.fn.DataTable.isDataTable(".table-mailbox-preview")) {
                        jQuery(".table-mailbox-preview").DataTable().clear().destroy(true);
                        jQuery(".table-mailbox-preview").find("tbody").empty();
                    }

                    init_btn_with_tooltips();
                    init_tabs_scrollable();
                    var webmailTableNotSortable = [0];

                    // Prevent auto-initialization of preview table (it is hidden initially)
                    // Only initialize the table that should be visible
                    // Debug log removed

                    if (preview_pane_enabled || (preview_pane_enabled === null && default_preview_pane == "1")) {
                        // Preview pane enabled - show it and initialize preview table
                        // Debug log removed
                        setTimeout(function() {
                            showPreviewPane();
                        }, 150);
                    } else {
                        // Preview pane disabled - initialize full table
                        // Debug log removed
                        setTimeout(function() {
                            var fullTable = jQuery(".table-mailbox");
                            // Debug log removed
                            if (fullTable.length && !jQuery.fn.DataTable.isDataTable(".table-mailbox")) {
                                // Debug log removed
                                // Build URL with current query parameters (including search_tag)
                                var tableUrl = admin_url + "mailbox/table/'.$group.'";
                                var urlParams = new URLSearchParams(window.location.search);
                                if (urlParams.toString()) {
                                    tableUrl += "?" + urlParams.toString();
                                }
                                // Initialize DataTable with pagination enabled
                                var tableElement = jQuery(".table-mailbox");

                                // First, try to initialize with initDataTable
                                initDataTable(tableElement, tableUrl, "undefined", webmailTableNotSortable, "undefined", [6, "desc"]);

                                // Then immediately check and fix pagination
                                setTimeout(function() {
                                    if (jQuery.fn.DataTable.isDataTable(tableElement)) {
                                        var dt = tableElement.DataTable();
                                        // Debug log removed

                                        // Force enable pagination with default 10 per page
                                        dt.settings()[0].oInit.paging = true;
                                        dt.settings()[0].oFeatures.bPaginate = true;
                                        // Set default to 10 if not already set by user
                                        if (!dt.settings()[0].oInit.pageLength || dt.settings()[0].oInit.pageLength === 25) {
                                            dt.settings()[0].oInit.pageLength = 10;
                                            dt.settings()[0]._iDisplayLength = 10;
                                            dt.page.len(10);
                                        }

                                        // Add event handler for length change to ensure server-side redraw
                                        tableElement.off(\'length.dt\').on(\'length.dt\', function(e, settings, len) {
                                            // Debug log removed
                                            // Debug log removed
                                            var dtInstance = tableElement.DataTable();
                                            var pageInfoBefore = dtInstance.page.info();
                                            // Debug log removed
                                            // Debug log removed
                                            // Debug log removed

                                            // Also listen to the xhr event to see what the server returns
                                            tableElement.off(\'xhr.dt\').on(\'xhr.dt\', function(e, settings, json, xhr) {
                                                // Debug log removed
                                                if (json === null || json === undefined) {
                                                    console.error("[Pagination Debug] AJAX response is NULL! Check Network tab for error details.");
                                                    // Debug log removed
                                                    // Debug log removed
                                                    return;
                                                }
                                                // Debug log removed
                                                // DataTables uses aaData property, not data property
                                                var recordsReturned = json[\'aaData\'] ? json[\'aaData\'].length : (json[\'data\'] ? json[\'data\'].length : 0);
                                                // Debug log removed
                                                // Debug log removed
                                                // Debug log removed
                                            });

                                            // Also listen for AJAX errors
                                            tableElement.off(\'error.dt\').on(\'error.dt\', function(e, settings, techNote, message) {
                                                console.error("[Pagination Debug] ===== AJAX ERROR =====");
                                                console.error("[Pagination Debug] Error message:", message);
                                                console.error("[Pagination Debug] Technical note:", techNote);
                                            });

                                            dtInstance.draw();

                                            setTimeout(function() {
                                                var pageInfoAfter = dtInstance.page.info();
                                                // Debug log removed
                                                // Debug log removed
                                            }, 500);
                                        });

                                        // Force server-side redraw (draw() without false triggers server request)
                                        dt.draw();

                                        // Debug log removed

                                        // Double-check after a short delay and force server-side redraw
                                        setTimeout(function() {
                                            var pageInfo = dt.page.info();
                                            // Debug log removed
                                            // Debug log removed

                                            if (pageInfo.pages <= 1 && pageInfo.recordsTotal > 10) {
                                                console.warn("[Pagination Debug] Pagination still not working! Records:", pageInfo.recordsTotal, "Pages:", pageInfo.pages);
                                                // Debug log removed

                                                // Check if server-side processing is enabled
                                                if (!dt.settings()[0].oFeatures.bServerSide) {
                                                    console.error("[Pagination Debug] Server-side processing is DISABLED! This is the problem.");
                                                    // Debug log removed

                                                    // Try to enable server-side processing
                                                    dt.settings()[0].oFeatures.bServerSide = true;
                                                    dt.settings()[0].oInit.serverSide = true;
                                                    dt.draw();
                                                } else {
                                                    // Debug log removed
                                                    // Don\'t override user\'s page length selection
                                                    // dt.page.len(10);
                                                    dt.draw();
                                                }

                                                // Check again after server response
                                                setTimeout(function() {
                                                    var newPageInfo = dt.page.info();
                                                    // Debug log removed
                                                    if (newPageInfo.pages <= 1 && newPageInfo.recordsTotal > 10) {
                                                        console.error("[Pagination Debug] Server-side pagination may not be working correctly.");
                                                        // Debug log removed
                                                    }
                                                }, 1000);
                                            }
                                        }, 500);
                                    } else {
                                        console.error("[Pagination Debug] DataTable not initialized!");
                                    }
                                }, 300);
                            } else {
                                // Debug log removed
                            }
                        }, 150);
                    }

                    appValidateForm($("#mailbox-config-form"), {
                        email: "required",
                        mail_password: "required",
                    });

                    // Advanced Search Functions
                    window.applyAdvancedSearch = function() {
                        var params = {};

                        if (jQuery(\'#search_from\').val()) {
                            params.search_from = jQuery(\'#search_from\').val();
                        }
                        if (jQuery(\'#search_subject\').val()) {
                            params.search_subject = jQuery(\'#search_subject\').val();
                        }
                        if (jQuery(\'#search_body\').val()) {
                            params.search_body = jQuery(\'#search_body\').val();
                        }
                        if (jQuery(\'#search_tag\').val()) {
                            params.search_tag = jQuery(\'#search_tag\').val();
                        }
                        if (jQuery(\'#search_date_from\').val()) {
                            params.search_date_from = jQuery(\'#search_date_from\').val();
                        }
                        if (jQuery(\'#search_date_to\').val()) {
                            params.search_date_to = jQuery(\'#search_date_to\').val();
                        }
                        if (jQuery(\'#search_has_attachment\').val() !== \'\') {
                            params.search_has_attachment = jQuery(\'#search_has_attachment\').val();
                        }
                        if (jQuery(\'#search_read\').val() !== \'\') {
                            params.search_read = jQuery(\'#search_read\').val();
                        }

                        // Build URL with search parameters
                        var isPreviewPane = jQuery("#mailbox_split_view").is(":visible");
                        var baseUrl = isPreviewPane ?
                            (admin_url + \'mailbox/table_preview/\' + (\'inbox\' == \''.$group.'\' ? \'inbox\' : \''.$group.'\')) :
                            (admin_url + \'mailbox/table/\' + (\'inbox\' == \''.$group.'\' ? \'inbox\' : \''.$group.'\'));
                        var queryParams = [];

                        for (var key in params) {
                            if (params.hasOwnProperty(key) && params[key] !== \'\') {
                                queryParams.push(key + \'=\' + encodeURIComponent(params[key]));
                            }
                        }

                        var searchUrl = baseUrl;
                        if (queryParams.length > 0) {
                            searchUrl += \'?\' + queryParams.join(\'&\');
                        }

                        // Reload active table (full or preview) with search parameters
                        var activeTable = isPreviewPane ?
                            jQuery(".table-mailbox-preview") : jQuery(".table-mailbox");

                        if (activeTable.length > 0 && jQuery.fn.DataTable && jQuery.fn.DataTable.isDataTable(activeTable)) {
                            var table = activeTable.DataTable();
                            table.ajax.url(searchUrl).load();
                        } else {
                            // If table not initialized yet, redirect with parameters
                            window.location.href = searchUrl;
                        }

                        return false; // Prevent form submission
                    };

                    window.clearAdvancedSearch = function() {
                        jQuery(\'#advanced_search_form\')[0].reset();
                        if (jQuery.fn.selectpicker) {
                            jQuery(\'.selectpicker\').selectpicker(\'refresh\');
                        }

                        // Reload active table without search parameters
                        var isPreviewPane = jQuery("#mailbox_split_view").is(":visible");
                        var activeTable = isPreviewPane ?
                            jQuery(".table-mailbox-preview") : jQuery(".table-mailbox");

                        if (activeTable.length > 0 && jQuery.fn.DataTable && jQuery.fn.DataTable.isDataTable(activeTable)) {
                            var table = activeTable.DataTable();
                            var baseUrl = isPreviewPane ?
                                (admin_url + \'mailbox/table_preview/\' + (\'inbox\' == \''.$group.'\' ? \'inbox\' : \''.$group.'\')) :
                                (admin_url + \'mailbox/table/\' + (\'inbox\' == \''.$group.'\' ? \'inbox\' : \''.$group.'\'));
                            table.ajax.url(baseUrl).load();
                        } else {
                            var currentUrl = window.location.href.split(\'?\')[0];
                            window.location.href = currentUrl;
                        }
                    };

                    window.toggleAdvancedSearch = function() {
                        var panel = jQuery(\'#advanced_search_panel\');
                        var chevron = jQuery(\'#advanced_search_chevron\');

                        if (panel.is(\':visible\')) {
                            panel.slideUp(300);
                            chevron.removeClass(\'fa-chevron-up\').addClass(\'fa-chevron-down\');
                        } else {
                            panel.slideDown(300);
                            chevron.removeClass(\'fa-chevron-down\').addClass(\'fa-chevron-up\');
                        }
                    };

                    // Column Visibility Toggle Functionality
                    (function() {
                        var storageKey = \'mailbox_column_visibility_\' + \''.$group.'\';

                        // Initialize column visibility from localStorage
                        function initializeColumnVisibility() {
                            // Check both main table and preview pane table
                            var table = jQuery(".table-mailbox:visible").first();
                            if (!table.length) {
                                table = jQuery(".table-mailbox-preview:visible").first();
                            }
                            if (!table.length || !jQuery.fn.DataTable.isDataTable(table)) {
                                setTimeout(initializeColumnVisibility, 100);
                                return;
                            }

                            var dt = table.DataTable();
                            var savedVisibility = localStorage.getItem(storageKey);
                            var columnVisibility = {};

                            if (savedVisibility) {
                                try {
                                    columnVisibility = JSON.parse(savedVisibility);
                                } catch(e) {
                                    console.warn("Error parsing column visibility:", e);
                                }
                            }

                            // Default: all visible except first (checkbox) is always visible
                            var columnCount = dt.columns().count();
                            for (var i = 0; i < columnCount; i++) {
                                var isVisible = (i === 0) ? true : (columnVisibility[i] !== false);
                                if (typeof columnVisibility[i] !== "undefined") {
                                    isVisible = columnVisibility[i];
                                }
                                dt.column(i).visible(isVisible);
                                updateColumnVisibilityIcon(i, isVisible);
                            }
                        }

                        // Update icon in dropdown menu
                        function updateColumnVisibilityIcon(columnIndex, isVisible) {
                            var menuItem = jQuery(\'#column_visibility_menu a[data-column="\' + columnIndex + \'"]\');
                            if (menuItem.length) {
                                var icon = menuItem.find(\'i\');
                                if (isVisible) {
                                    icon.removeClass(\'fa-square\').addClass(\'fa-check\');
                                } else {
                                    icon.removeClass(\'fa-check\').addClass(\'fa-square\');
                                }
                            }
                        }

                        // Toggle column visibility
                        function toggleColumnVisibility(columnIndex) {
                            // Prevent hiding column 0 (checkbox column)
                            if (columnIndex === 0) {
                                return;
                            }

                            // Check both main table and preview pane table
                            var table = jQuery(".table-mailbox:visible").first();
                            if (!table.length) {
                                table = jQuery(".table-mailbox-preview:visible").first();
                            }
                            if (!table.length || !jQuery.fn.DataTable.isDataTable(table)) {
                                return;
                            }

                            var dt = table.DataTable();
                            var isVisible = dt.column(columnIndex).visible();
                            dt.column(columnIndex).visible(!isVisible);

                            updateColumnVisibilityIcon(columnIndex, !isVisible);

                            // Save to localStorage
                            var savedVisibility = localStorage.getItem(storageKey);
                            var columnVisibility = {};
                            if (savedVisibility) {
                                try {
                                    columnVisibility = JSON.parse(savedVisibility);
                                } catch(e) {
                                    console.warn("Error parsing column visibility:", e);
                                }
                            }
                            columnVisibility[columnIndex] = !isVisible;
                            localStorage.setItem(storageKey, JSON.stringify(columnVisibility));
                        }

                        // Show all columns
                        function showAllColumns() {
                            // Check both main table and preview pane table
                            var table = jQuery(".table-mailbox:visible").first();
                            if (!table.length) {
                                table = jQuery(".table-mailbox-preview:visible").first();
                            }
                            if (!table.length || !jQuery.fn.DataTable.isDataTable(table)) {
                                return;
                            }

                            var dt = table.DataTable();
                            var columnCount = dt.columns().count();
                            var columnVisibility = {};

                            for (var i = 0; i < columnCount; i++) {
                                dt.column(i).visible(true);
                                updateColumnVisibilityIcon(i, true);
                                columnVisibility[i] = true;
                            }

                            localStorage.setItem(storageKey, JSON.stringify(columnVisibility));
                        }

                        // Hide all columns except first (checkbox) and subject (column 3)
                        function hideAllColumns() {
                            // Check both main table and preview pane table
                            var table = jQuery(".table-mailbox:visible").first();
                            if (!table.length) {
                                table = jQuery(".table-mailbox-preview:visible").first();
                            }
                            if (!table.length || !jQuery.fn.DataTable.isDataTable(table)) {
                                return;
                            }

                            var dt = table.DataTable();
                            var columnCount = dt.columns().count();
                            var columnVisibility = {};

                            for (var i = 0; i < columnCount; i++) {
                                // Keep checkbox (0) and subject (3) visible
                                var keepVisible = (i === 0 || i === 3);
                                dt.column(i).visible(keepVisible);
                                updateColumnVisibilityIcon(i, keepVisible);
                                columnVisibility[i] = keepVisible;
                            }

                            localStorage.setItem(storageKey, JSON.stringify(columnVisibility));
                        }

                        // Event handlers
                        jQuery(document).ready(function() {
                            // Column toggle handlers
                            jQuery(document).on(\'click\', \'#column_visibility_menu a[data-column]\', function(e) {
                                e.preventDefault();
                                var columnIndex = parseInt(jQuery(this).data(\'column\'));
                                toggleColumnVisibility(columnIndex);
                            });

                            // Show all columns
                            jQuery(document).on(\'click\', \'#show_all_columns\', function(e) {
                                e.preventDefault();
                                showAllColumns();
                            });

                            // Hide all columns
                            jQuery(document).on(\'click\', \'#hide_all_columns\', function(e) {
                                e.preventDefault();
                                hideAllColumns();
                            });

                            // Initialize after table is loaded
                            setTimeout(initializeColumnVisibility, 500);

                            // Also initialize when DataTable is drawn (both main and preview tables)
                            jQuery(document).on(\'draw.dt\', \'.table-mailbox, .table-mailbox-preview\', function() {
                                initializeColumnVisibility();
                            });
                        });
                    })();

                    // Density Toggle Functionality (Comfortable, Cozy, Compact)
                    (function() {
                        var densityStorageKey = \'mailbox_density\';
                        var currentDensity = localStorage.getItem(densityStorageKey) || \'comfortable\';

                        // Apply density
                        function applyDensity(density) {
                            var $body = jQuery("body");
                            var $mailbox = jQuery("#mailbox");
                            var $tables = jQuery(".table-mailbox, .table-mailbox-preview");

                            // Remove all density classes
                            $body.removeClass("mailbox-density-comfortable mailbox-density-cozy mailbox-density-compact");
                            $mailbox.removeClass("mailbox-density-comfortable mailbox-density-cozy mailbox-density-compact");
                            $tables.removeClass("mailbox-density-comfortable mailbox-density-cozy mailbox-density-compact");

                            // Add selected density class
                            $body.addClass("mailbox-density-" + density);
                            $mailbox.addClass("mailbox-density-" + density);
                            $tables.addClass("mailbox-density-" + density);

                            // Update dropdown display
                            jQuery("#density_display_text").text(jQuery("#density_" + density).text().trim());

                            // Update checkmarks in dropdown
                            jQuery("#density_menu a").each(function() {
                                var $item = jQuery(this);
                                var $icon = $item.find("i.fa");
                                if ($item.data("density") === density) {
                                    $icon.removeClass("fa").addClass("fa fa-check");
                                } else {
                                    $icon.removeClass("fa-check");
                                }
                            });

                            // Save to localStorage
                            localStorage.setItem(densityStorageKey, density);
                        }

                        // Initialize density on page load
                        jQuery(document).ready(function() {
                            applyDensity(currentDensity);

                            // Handle density selection
                            jQuery(document).on(\'click\', \'#density_menu a[data-density]\', function(e) {
                                e.preventDefault();
                                var density = jQuery(this).data("density");
                                applyDensity(density);
                            });
                        });
                    })();

                    $("body").on("change", ".mail-tag", function (event, state) {
                        let mail_id = $(this).data("id");
                        let mail_type = $(this).data("type");
                        let tag_id = $(this).find("option:selected").data("id");
                        if (typeof tag_id === "undefined") {
                            tag_id = 0;
                        }
                        $.ajax({
                            url: admin_url + "mailbox/update_mail_tag/" + mail_id + "/" + tag_id + "/" + mail_type
                        }).done(function(response) {
                            response = JSON.parse(response);
                            if (response.success) {
                                alert_float("success", response.message);
                            }
                        }).fail(function(error) {
                            alert_float("danger", JSON.parse(error.responseText));
                        });
                    });

                    // Handle multiple tags change (for Bootstrap Select dropdown)
                    // Use a debounce function to prevent multiple rapid calls
                    var tagUpdateTimeout = null;
                    function updateMailTags($select) {
                        // Clear any pending updates
                        if (tagUpdateTimeout) {
                            clearTimeout(tagUpdateTimeout);
                        }

                        // Get selected tags - ensure it\'s always an array
                        var selected_tags = $select.val();
                        if (!selected_tags) {
                            selected_tags = [];
                        } else if (!Array.isArray(selected_tags)) {
                            selected_tags = [selected_tags];
                        }

                        // Try multiple ways to get data attributes
                        // First try direct data attributes on select element (using attr() to get actual HTML attributes)
                        var mail_id = $select.attr("data-id");
                        var type = $select.attr("data-type");

                        console.log("Initial data extraction:", {mail_id: mail_id, type: type, select_name: $select.attr("name")});

                        // If data attributes not found on select, try to get from the Bootstrap Select wrapper
                        if (!mail_id || !type) {
                            var $wrapper = $select.closest(\'.bootstrap-select\');
                            if ($wrapper.length > 0) {
                                var wrapper_id = $wrapper.attr("data-id");
                                var wrapper_type = $wrapper.attr("data-type");
                                console.log("Checking wrapper:", {wrapper_id: wrapper_id, wrapper_type: wrapper_type});
                                if (!mail_id && wrapper_id) {
                                    mail_id = wrapper_id;
                                    // Also copy back to select element for future use
                                    $select.attr("data-id", wrapper_id);
                                }
                                if (!type && wrapper_type) {
                                    type = wrapper_type;
                                    // Also copy back to select element for future use
                                    $select.attr("data-type", wrapper_type);
                                }
                            }
                        }

                        // Try to get from select name attribute as last resort (format: mail_tag_select_123 or mail_tag_select_preview_123)
                        if (!mail_id && $select.attr("name")) {
                            var nameMatch = $select.attr("name").match(/mail_tag_select[^_]*_(\d+)/);
                            if (nameMatch && nameMatch[1]) {
                                mail_id = nameMatch[1];
                                console.log("Extracted mail_id from name:", mail_id);
                            }
                        }

                        // If type is still missing, try to infer from URL or context
                        if (!type) {
                            var currentUrl = window.location.href;
                            if (currentUrl.indexOf(\'/mailbox/inbox/\') !== -1 || currentUrl.indexOf(\'/inbox/\') !== -1) {
                                type = \'inbox\';
                                console.log("Inferred type from URL: inbox");
                            } else if (currentUrl.indexOf(\'/mailbox/outbox/\') !== -1 || currentUrl.indexOf(\'/outbox/\') !== -1) {
                                type = \'outbox\';
                                console.log("Inferred type from URL: outbox");
                            } else {
                                type = \'inbox\'; // Default to inbox
                                console.log("Using default type: inbox");
                            }
                        }

                        console.log("Final extracted values:", {mail_id: mail_id, type: type});

                        // Only proceed if we have valid data attributes
                        if (!mail_id) {
                            console.error("Missing email ID for tag update:", {
                                mail_id: mail_id,
                                type: type,
                                select_name: $select.attr("name"),
                                select_id: $select.attr("id"),
                                select_html: $select[0] ? $select[0].outerHTML.substring(0, 200) : \'N/A\'
                            });
                            alert_float("warning", "Cannot update tags: Missing email ID");
                            return;
                        }

                        if (!type) {
                            console.error("Missing type for tag update:", {
                                mail_id: mail_id,
                                type: type,
                                select_name: $select.attr("name")
                            });
                            alert_float("warning", "Cannot update tags: Missing email type");
                            return;
                        }

                        console.log("Updating tags for email:", mail_id, "Type:", type, "Tags:", selected_tags, "URL:", admin_url + "mailbox/update_mail_tag/" + mail_id + "/");

                        // Debounce the update to prevent rapid-fire requests
                        tagUpdateTimeout = setTimeout(function() {
                            // Get CSRF token - try multiple methods
                            var csrfToken = null;
                            var csrfTokenName = "csrf_token_name";

                            // Method 1: jQuery
                            var csrfMeta = jQuery("meta[name=\"csrf-token\"]");
                            if (csrfMeta.length > 0) {
                                csrfToken = csrfMeta.attr("content");
                            }

                            // Method 2: Native DOM if jQuery failed
                            if (!csrfToken) {
                                var metaEl = document.querySelector("meta[name=\"csrf-token\"]");
                                if (metaEl) {
                                    csrfToken = metaEl.getAttribute("content");
                                }
                            }

                            // Get token name
                            var csrfNameMeta = jQuery("meta[name=\"csrf-token-name\"]");
                            if (csrfNameMeta.length > 0) {
                                csrfTokenName = csrfNameMeta.attr("content");
                            } else {
                                var nameMetaEl = document.querySelector("meta[name=\"csrf-token-name\"]");
                                if (nameMetaEl) {
                                    csrfTokenName = nameMetaEl.getAttribute("content");
                                }
                            }

                            console.log("CSRF token check:", {
                                token: csrfToken ? csrfToken.substring(0, 20) + "..." : "NOT FOUND",
                                tokenName: csrfTokenName,
                                metaTagExists: jQuery("meta[name=\"csrf-token\"]").length > 0 || document.querySelector("meta[name=\"csrf-token\"]") !== null
                            });

                            var postData = {
                                tag_id: selected_tags,
                                type: type
                            };

                            // Try to get CSRF token from form input (Perfex CRM might use form inputs)
                            if (!csrfToken) {
                                var csrfInput = jQuery("input[name^=\"csrf\"]").first();
                                if (csrfInput.length > 0) {
                                    csrfToken = csrfInput.val();
                                    csrfTokenName = csrfInput.attr("name");
                                    console.log("Found CSRF token in form input:", csrfTokenName);
                                }
                            }

                            // Add CSRF token to POST data if found
                            if (csrfToken) {
                                postData[csrfTokenName] = csrfToken;
                                console.log("CSRF token added to POST data");
                            } else {
                                console.warn("CSRF token not found in meta tag or form. Relying on ajaxSetup header.");
                            }

                            console.log("Sending AJAX request:", Object.keys(postData), "CSRF included:", !!csrfToken);

                            // Use $.ajax instead of $.post to ensure headers are set properly
                            $.ajax({
                                url: admin_url + "mailbox/update_mail_tag/" + mail_id + "/",
                                type: "POST",
                                data: postData,
                                dataType: "json",
                                beforeSend: function(xhr) {
                                    // Ensure CSRF header is set if token exists
                                    if (csrfToken) {
                                        xhr.setRequestHeader("X-CSRF-TOKEN", csrfToken);
                                    }
                                }
                            }).done(function(response) {
                                try {
                                    response = typeof response === \'string\' ? JSON.parse(response) : response;
                                    console.log("Tag update response:", response);
                                    if (response.success) {
                                        alert_float("success", response.message);
                                        // Reload the page to show updated tags (for single view)
                                        var currentUrl = window.location.href;
                                        console.log("Current URL:", currentUrl);
                                        if (currentUrl.indexOf(\'/mailbox/inbox/\') !== -1 || currentUrl.indexOf(\'/mailbox/outbox/\') !== -1) {
                                            // We\'re in single view - reload to show updated tags
                                            console.log("Reloading page for single email view");
                                            setTimeout(function() {
                                                window.location.reload();
                                            }, 500);
                                        } else {
                                            // We\'re in list view - just reload tables
                                            console.log("Reloading tables for list view");
                                            reload_mailbox_tables();
                                            // Also reload preview if it\'s open and showing the same email
                                            var previewEmailId = jQuery("#preview_content").data("email-id");
                                            if (previewEmailId && previewEmailId == mail_id) {
                                                setTimeout(function() {
                                                    loadEmailPreview(mail_id);
                                                }, 300);
                                            }
                                        }
                                    } else {
                                        console.error("Tag update failed:", response);
                                        alert_float("warning", response.message || "Error updating tags");
                                    }
                                } catch (e) {
                                    console.error("Error parsing response:", e, response);
                                    alert_float("danger", "Error updating tags");
                                }
                            }).fail(function(xhr, status, error) {
                                console.error("AJAX error:", status, error, xhr.responseText);
                                if (xhr.status === 419) {
                                    console.warn("CSRF token expired (419). Trying to get fresh token from form...");
                                    // Try to get fresh token from form input
                                    var freshToken = jQuery("input[name^=\"csrf\"]").first().val();
                                    var freshTokenName = jQuery("input[name^=\"csrf\"]").first().attr("name") || "csrf_token_name";
                                    if (!freshToken) {
                                        // Try meta tag as fallback
                                        freshToken = jQuery("meta[name=\"csrf-token\"]").attr("content");
                                        freshTokenName = jQuery("meta[name=\"csrf-token-name\"]").attr("content") || "csrf_token_name";
                                    }
                                    if (freshToken) {
                                        postData[freshTokenName] = freshToken;
                                        $.ajax({
                                            url: admin_url + "mailbox/update_mail_tag/" + mail_id + "/",
                                            type: "POST",
                                            data: postData,
                                            dataType: "json",
                                            beforeSend: function(xhr) {
                                                xhr.setRequestHeader("X-CSRF-TOKEN", freshToken);
                                            }
                                        }).done(function(response) {
                                            try {
                                                response = typeof response === \'string\' ? JSON.parse(response) : response;
                                                if (response.success) {
                                                    alert_float("success", response.message);
                                                    var currentUrl = window.location.href;
                                                    if (currentUrl.indexOf(\'/mailbox/inbox/\') !== -1 || currentUrl.indexOf(\'/mailbox/outbox/\') !== -1) {
                                                        setTimeout(function() {
                                                            window.location.reload();
                                                        }, 500);
                                                    } else {
                                                        reload_mailbox_tables();
                                                    }
                                                }
                                            } catch (e) {
                                                console.error("Error parsing retry response:", e);
                                            }
                                        });
                                    } else {
                                        alert_float("danger", "CSRF token not found. Please refresh the page.");
                                    }
                                } else {
                                    alert_float("danger", "Error updating tags: " + error);
                                }
                            });
                        }, 300); // 300ms debounce
                    }

                    // Primary handler for Bootstrap Select custom event
                    // Use selector that matches selects with mail_tag_select name pattern OR data-id/data-type attributes
                    // Bootstrap Select may strip the mail-tag-multiple class, so we use a more reliable selector
                    $("body").on("changed.bs.select", "select[name^=\'mail_tag_select\'], select[data-id][data-type]", function (event, clickedIndex, isSelected, previousValue) {
                        var $select = $(this);
                        updateMailTags($select);
                    });

                    // Also listen directly on change event as backup (Bootstrap Select should trigger this too)
                    $("body").on("change", "select[name^=\'mail_tag_select\'], select[data-id][data-type]", function (event) {
                        var $select = $(this);
                        // Skip if this is triggered by our code or if Bootstrap Select has not initialized
                        if (event.isTrigger || !$select.hasClass(\'selectpicker\')) {
                            return;
                        }
                        updateMailTags($select);
                    });

                    // Also handle when Bootstrap Select initializes and moves attributes to wrapper
                    $("body").on("loaded.bs.select", "select[name^=\'mail_tag_select\'], select[data-id][data-type]", function() {
                        var $select = $(this);
                        var $wrapper = $select.closest(\'.bootstrap-select\');
                        // Ensure data attributes are copied to wrapper if they exist on select
                        if ($select.attr("data-id") && !$wrapper.attr("data-id")) {
                            $wrapper.attr("data-id", $select.attr("data-id"));
                        }
                        if ($select.attr("data-type") && !$wrapper.attr("data-type")) {
                            $wrapper.attr("data-type", $select.attr("data-type"));
                        }
                    });

                    // Fallback handler for standard change event (fires when Bootstrap Select updates)
                    $("body").on("change", "select[name^=\'mail_tag_select\'], select[data-id][data-type]", function (event) {
                        var $select = $(this);
                        // Only process if this is a real change (not triggered by our code)
                        if (event.isTrigger) {
                            return;
                        }
                        // Skip if Bootstrap Select is handling it
                        if ($select.hasClass("selectpicker")) {
                            return;
                        }
                        console.log("Standard change event fired for tag select", {
                            mail_id: $select.attr("data-id"),
                            type: $select.attr("data-type"),
                            selected_tags: $select.val()
                        });
                        updateMailTags($select);
                    });

                    $("body").on("change", ".mail-template", function (event, state) {
                        let mail_id = $(this).data("id");
                        let mail_type = $(this).data("type");
                        let template_id = $(this).find("option:selected").data("id");
                        if (typeof template_id === "undefined") {
                            template_id = 0;
                        }
                        $.ajax({
                            url: admin_url + "mailbox/update_mail_template/" + mail_id + "/" + template_id + "/" + mail_type
                        }).done(function(response) {
                            response = JSON.parse(response);
                            if (response.success) {
                                alert_float("success", response.message);
                            }
                        }).fail(function(error) {
                            alert_float("danger", JSON.parse(error.responseText));
                        });
                    });

                });
            }

            // Inject CSS directly via JavaScript to ensure checkbox sizing
            function injectDebugCSS() {
                var style = document.createElement("style");
                style.id = "mailbox-checkbox-css";
                style.textContent = `
                    /* Force checkbox label:before to 18px with maximum specificity */
                    .checkbox label:before,
                    div.checkbox label:before,
                    .table-mailbox .checkbox label:before,
                    .table-mailbox-preview .checkbox label:before,
                    table.dataTable .checkbox label:before,
                    .dataTables_wrapper .table-mailbox .checkbox label:before,
                    .dataTables_wrapper .table-mailbox-preview .checkbox label:before,
                    .mail-row-actions .checkbox label:before,
                    table.dataTable tbody tr .checkbox label:before,
                    table.dataTable thead tr .checkbox label:before,
                    body .checkbox label:before,
                    body div.checkbox label:before,
                    body .table-mailbox .checkbox label:before,
                    body .table-mailbox-preview .checkbox label:before,
                    * .checkbox label:before,
                    * div.checkbox label:before {
                        width: 18px !important;
                        height: 18px !important;
                        min-width: 18px !important;
                        min-height: 18px !important;
                        max-width: 18px !important;
                        max-height: 18px !important;
                        box-sizing: border-box !important;
                    }

                    /* Force checkbox margin-top for screens >= 640px */
                    @media (min-width: 640px) {
                        table.dataTable tbody tr:has(.checkbox) > td > .checkbox,
                        table.dataTable thead:has(.checkbox) th > .checkbox,
                        .dataTables_wrapper table.dataTable tbody tr:has(.checkbox) > td > .checkbox,
                        .dataTables_wrapper table.dataTable thead:has(.checkbox) th > .checkbox,
                        .table-mailbox tbody tr:has(.checkbox) > td > .checkbox,
                        .table-mailbox-preview tbody tr:has(.checkbox) > td > .checkbox,
                        .table-mailbox thead tr:has(.checkbox) th > .checkbox,
                        .table-mailbox-preview thead tr:has(.checkbox) th > .checkbox {
                            margin-top: 3px !important;
                        }
                    }
                `;
                document.head.appendChild(style);
            }

            // Inject CSS immediately - call BEFORE initMailboxInlineScripts
            try {
                if (document.head) {
                    injectDebugCSS();
                } else {
                    document.addEventListener("DOMContentLoaded", function() {
                        injectDebugCSS();
                    });
                }

                // Also inject after DOMContentLoaded to ensure it runs
                if (document.readyState === "loading") {
                    document.addEventListener("DOMContentLoaded", function() {
                        injectDebugCSS();
                    });
                } else {
                    injectDebugCSS();
                }
            } catch(e) {
                // Silently fail
            }

            // Wait a bit for all scripts from init_tail to load, then initialize
            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", function() {
                    setTimeout(initMailboxInlineScripts, 200);
                });
            } else {
                setTimeout(initMailboxInlineScripts, 200);
            }
        })();

        // Auto-refresh functionality
        (function() {
            var autoRefreshInterval = '.((int)get_option('mailbox_auto_refresh_interval') ?: 0).'; // In seconds

            if (autoRefreshInterval > 0) {
                setInterval(function() {
                    // Check if user is viewing mailbox page
                    if (window.location.href.indexOf("admin/mailbox") === -1) {
                        return; // Don\'t refresh if not on mailbox page
                    }

                    // Reload the current DataTable (either preview or full table)
                    var activeTable = null;
                    if (jQuery("#mailbox_split_view").is(":visible")) {
                        // Preview pane is active
                        var previewTable = jQuery(".table-mailbox-preview");
                        if (previewTable.length > 0 && jQuery.fn.DataTable.isDataTable(previewTable)) {
                            activeTable = previewTable.DataTable();
                        }
                    } else {
                        // Full table is active
                        var fullTable = jQuery(".table-mailbox");
                        if (fullTable.length > 0 && jQuery.fn.DataTable.isDataTable(fullTable)) {
                            activeTable = fullTable.DataTable();
                        }
                    }

                    if (activeTable) {
                        // Reload data without resetting pagination
                        activeTable.ajax.reload(null, false);
                    }
                }, autoRefreshInterval * 1000); // Convert to milliseconds
            }
        })();
        </script>';
    }
}

/**
 * Init mailbox module menu items in setup in admin_init hook.
 *
 * @return null
 */
function mailbox_module_init_menu_items()
{
    $CI = &get_instance();
    if ('1' == get_option('mailbox_enabled')) {
        $badge      = '';
        // Build where conditions for unread count
        $where_unread = ['read' => '0', 'to_staff_id' => get_staff_user_id(), 'trash' => '0'];
        // Only add archived condition if column exists
        if ($CI->db->field_exists('archived', db_prefix() . 'mail_inbox')) {
            $where_unread['archived'] = '0';
        }
        $num_unread = total_rows(db_prefix().'mail_inbox', $where_unread);
        if ($num_unread > 0) {
            $badge = ' <span class="label" style="background-color: red; color: white;">' . $num_unread . '</span>';
        }

        $CI->app_menu->add_sidebar_menu_item('mailbox', [
            'name'     => _l('mailbox'),
            'icon'     => 'fa fa-envelope-square',
            'href'     => admin_url('mailbox'),
            'position' => 6,
            'badge'    => $num_unread > 0 ? [
                'value' => $num_unread,
                'type'  => 'danger',
            ] : [],
        ]);

        $CI->app_menu->add_sidebar_children_item('mailbox', [
            'slug'     => 'mailbox-emails',
            'name'     => _l('mailbox_emails'),
            'href'     => admin_url('mailbox'),
            'position' => 1,
            'badge'    => [],
        ]);
        $CI->app_menu->add_sidebar_children_item('mailbox', [
            'slug'     => 'mailbox-tags',
            'name'     => _l('mailbox_tags'),
            'href'     => admin_url('mailbox/tags'),
            'position' => 2,
            'badge'    => [],
        ]);
        $CI->app_menu->add_sidebar_children_item('mailbox', [
            'slug'     => 'mailbox-contacts',
            'name'     => _l('contacts'),
            'href'     => admin_url('mailbox/contacts'),
            'position' => 3,
            'badge'    => [],
        ]);
        $CI->app_menu->add_sidebar_children_item('mailbox', [
            'slug'     => 'mailbox-email-templates',
            'name'     => _l('email_templates'),
            'href'     => admin_url('mailbox/email_templates'),
            'position' => 4,
            'badge'    => [],
        ]);
        $CI->app_menu->add_sidebar_children_item('mailbox', [
            'slug'     => 'mailbox-auto-replies',
            'name'     => _l('mailbox_auto_replies'),
            'href'     => admin_url('mailbox/auto_replies'),
            'position' => 5,
            'badge'    => [],
        ]);

        // Add Rules submenu (only if enabled and table exists)
        $rules_enabled = get_option('mailbox_enable_rules');
        if (($rules_enabled === '' || $rules_enabled === null || $rules_enabled == '1') &&
            $CI->db->table_exists(db_prefix() . 'mail_rules')) {
            $CI->app_menu->add_sidebar_children_item('mailbox', [
                'slug'     => 'mailbox-rules',
                'name'     => _l('mailbox_rules'),
                'href'     => admin_url('mailbox/rules_index'),
                'position' => 5.5,
                'badge'    => [],
            ]);
        }

        $CI->app_menu->add_sidebar_children_item('mailbox', [
            'slug'     => 'mailbox-config',
            'name'     => _l('mailbox_config'),
            'href'     => admin_url('mailbox/config'),
            'position' => 6,
            'badge'    => [],
        ]);

        // Settings submenu - Admin only
        if (is_admin()) {
            $CI->app_menu->add_sidebar_children_item('mailbox', [
                'slug'     => 'mailbox-settings',
                'name'     => _l('settings'),
                'href'     => admin_url('settings?group=mailbox_settings'),
                'position' => 7,
                'badge'    => [],
            ]);
        }
    }
}

/**
 * Init mailbox module setting menu items in setup in admin_init hook.
 *
 * @return null
 */
hooks()->add_action('admin_init', 'mailbox_add_settings_tab');

function mailbox_add_settings_tab()
{
    $CI = &get_instance();
    // Use new method for Perfex CRM 3.2.0+
    if (method_exists($CI->app, 'add_settings_section')) {
        $CI->app->add_settings_section('mailbox-settings', [
            'title'    => _l('mailbox'),
            'icon'     => 'fa fa-inbox',
            'position' => 36,
            'children' => [
                [
                    'slug'     => 'mailbox-settings',
                    'name'     => _l('mailbox_setting'),
                    'view'     => 'mailbox/mailbox_settings',
                    'position' => 1,
                ],
            ],
        ]);
    } else {
        // Fallback for older versions
        $CI->app_tabs->add_settings_tab('mailbox-settings', [
            'name'     => _l('mailbox'),
            'icon'     => 'fa fa-inbox',
            'view'     => 'mailbox/mailbox_settings',
            'position' => 36,
        ]);
    }
}

/**
 * mailbox migration tables to replace old links description.
 *
 * @param array $tables
 *
 * @return array
 */
function mailbox_migration_tables_to_replace_old_links($tables)
{
    $tables[] = [
        'table' => db_prefix().'mail_inbox',
        'field' => 'description',
    ];

    return $tables;
}

/**
 * Scan mailbox from mail-server.
 *
 * @return [bool] [true/false]
 */
function scan_email_server()
{
    $enabled      = get_option('mailbox_enabled');
    $imap_server  = get_option('mailbox_imap_server');
    $encryption   = get_option('mailbox_encryption');
    $folder_scan  = get_option('mailbox_folder_scan');
    $check_every  = '1';

    if (1 == $enabled && strlen($imap_server) > 0) {
        $CI = &get_instance();

        // Get staff with either password or OAuth configured
        $CI->db->select()->from(db_prefix() . 'staff')
            ->group_start()
                ->where(db_prefix() . 'staff.mail_password !=', '')
                ->or_where(db_prefix() . 'staff.auth_method', 'oauth2')
            ->group_end();
        $staffs = $CI->db->get()->result_array();

        $inbox_email_ids = [];

        include_once __DIR__ . '/third_party/simple_html_dom.php';

        foreach ($staffs as $staff) {
            $last_run    = $staff['last_email_check'] ?? 0;
            $staff_email = $staff['email'];
            $staff_id    = $staff['staffid'];
            $auth_method = $staff['auth_method'] ?? 'password';

            // Skip if checked recently (prevent too frequent scans)
            if (!empty($last_run) && (time() <= $last_run + ($check_every * 60))) {
                continue;
            }

            // Update last check time BEFORE processing to prevent duplicate scans if cron runs multiple times quickly
            $CI->db->where('staffid', $staff_id);
            $CI->db->update(db_prefix().'staff', [
                'last_email_check' => time(),
            ]);

		// Determine IMAP settings: staff-specific overrides global settings
		$staff_imap_server = !empty($staff['imap_server']) ? $staff['imap_server'] : $imap_server;
		$staff_encryption = !empty($staff['imap_encryption']) ? $staff['imap_encryption'] : $encryption;
		$staff_port = !empty($staff['imap_port']) ? $staff['imap_port'] : ($staff_encryption === 'ssl' ? 993 : 143);
		$staff_auth_method = $auth_method; // For logging

		// Determine import preferences: per-staff setting (defaults to 1 = unread only)
		$staff_unseen_email = isset($staff['only_loop_on_unseen_emails']) ? $staff['only_loop_on_unseen_emails'] : 1;

		// Determine sync read status to server preference: per-staff setting (defaults to 0 = disabled)
		$staff_sync_read_status = isset($staff['sync_read_status_to_server']) ? $staff['sync_read_status_to_server'] : 0;

		// Log removed for performance - was showing too many logs per cron run

		// Use ImapService for IMAP connections (supports both OAuth and password authentication)
		try {
			$imapService = new \modules\mailbox\services\ImapService([
				'imap_server' => $staff_imap_server,
				'encryption' => $staff_encryption,
				'port' => $staff_port,
			]);

			if (!$imapService->connect($staff)) {
				$error = $imapService->getError();
				log_activity('IMAP connection failed for ' . $staff_email . '. Error: ' . $error, null);
				log_activity('IMAP connection details - Server: ' . $staff_imap_server . ':' . $staff_port . ', Encryption: ' . $staff_encryption . ', Auth: ' . $staff_auth_method, null);
				continue;
			}

			// IMAP connected successfully (log removed to minimize activity log noise)

			$imap = $imapService; // Use service for compatibility
			$usingImapService = true;
		} catch (Exception $e) {
			log_activity('IMAP connection failed for ' . $staff_email . '. Exception: ' . $e->getMessage(), null);
			// Log previous exception if exists (for detailed debugging)
			$previous = $e->getPrevious();
			if ($previous) {
				log_activity('IMAP underlying error: ' . $previous->getMessage(), null);
			}
			log_activity('IMAP connection details - Server: ' . $staff_imap_server . ':' . $staff_port . ', Encryption: ' . $staff_encryption . ', Auth: ' . $staff_auth_method, null);
			continue;
		}

			// Get folders to sync (multi-folder support)
			$folders_to_sync = [];
			if (function_exists('get_mailbox_folders_to_sync')) {
				$folders_to_sync = get_mailbox_folders_to_sync();
			} else {
				// Fallback to single folder (backward compatibility)
				$folders_to_sync[$folder_scan] = 'inbox';
			}

			// Loop through each folder
			foreach ($folders_to_sync as $imap_folder_name => $system_folder_type) {
				try {
					// Select folder
					if (!$imap->selectFolder($imap_folder_name)) {
						continue; // Skip this folder and continue with next
					}

					// Get last UID for incremental sync (only for ImapService)
					$last_uid = 0;
					if ($usingImapService && function_exists('get_mailbox_folder_last_uid')) {
						$last_uid = get_mailbox_folder_last_uid($staff_id, $imap_folder_name);
					}

					// Fetch emails
					// IMPORTANT: Always fetch unread emails if staff_unseen_email=1, regardless of last_uid
					// Incremental sync is only for fetching ALL emails, not unread ones
					$emails = [];
					if (1 == $staff_unseen_email) {
						// Always get unread messages - don't use incremental sync for unread emails
						$emails = $imap->getUnreadMessages();
					} else {
						// Fetch all emails - use incremental sync if available
						if ($usingImapService && $last_uid > 0 && method_exists($imap, 'getMessagesSinceUid')) {
							$emails = $imap->getMessagesSinceUid($last_uid);
						} else {
							$emails = $imap->getMessages();
						}
					}

					// Debug log removed to minimize activity log noise

					if (empty($emails)) {
						continue; // Skip to next folder
					}

					// Track highest UID for this folder (for ImapService only)
					$highest_uid = 0;

					// Processing emails (log removed for performance)

					foreach ($emails as $emailIndex => $email) {
				try {
					$email_uid = isset($email['uid']) ? (int)$email['uid'] : 0;

					// Track highest UID (for ImapService only)
					if ($usingImapService && $email_uid > 0 && $email_uid > $highest_uid) {
						$highest_uid = $email_uid;
					}

					// Get body using appropriate method
				if (method_exists($imap, 'getBody')) {
					$bodyData = $imap->getBody($email['uid']);
				} else {
					// Fallback for old Imap class
					$bodyData = $imap->getBody($email['uid']);
				}
				$email['body'] = isset($bodyData['body']) ? trim($bodyData['body']) : null;

				// Process HTML body if it's HTML
				if (isset($bodyData['html']) && $bodyData['html'] && !empty($email['body'])) {
					// HTML body - process it through the HTML sanitizer (using staff's HTML preference)
					$email['body'] = prepare_imap_email_body_html($email['body'], $staff_id);
				} elseif (!empty($email['body'])) {
					// Plain text body - convert newlines to <br> and escape HTML
					$email['body'] = nl2br(htmlspecialchars($email['body'], ENT_QUOTES, 'UTF-8'));
				}

				$email['body'] = handle_google_drive_links_in_text($email['body']);
				$data['attachments'] = [];
				if (isset($email['attachments']) && is_array($email['attachments']) && count($email['attachments']) > 0) {
					foreach ($email['attachments'] as $key => $at) {
						// Get attachment content first to get the real filename
						$_at_name = 'attachment_' . ($key + 1); // Temporary fallback

						// Get attachment content
						if (method_exists($imap, 'getAttachment')) {
							$_attachment = $imap->getAttachment($email['uid'], $key);

							if ($_attachment === false) {
								$email['attachments'][$key]['data'] = '';
								$email['attachments'][$key]['filename'] = $_at_name;
							} elseif (isset($_attachment['content']) && !empty($_attachment['content'])) {
								$email['attachments'][$key]['data'] = $_attachment['content'];

								// Get the real filename from getAttachment (preferred) or from original attachment data
								// Always prefer the name from getAttachment if it's not the fallback
								if (isset($_attachment['name']) && !empty($_attachment['name'])) {
									$retrieved_name = trim($_attachment['name']);
									// Only use it if it's not the fallback pattern
									if ($retrieved_name != 'attachment_' . ($key + 1) && !preg_match('/^attachment_\d+$/', $retrieved_name)) {
										$_at_name = $retrieved_name;
									} elseif (isset($email['attachments'][$key]['name']) && !empty($email['attachments'][$key]['name'])) {
										$_at_name = trim($email['attachments'][$key]['name']);
									} elseif (isset($email['attachments'][$key]['filename']) && !empty($email['attachments'][$key]['filename'])) {
										$_at_name = trim($email['attachments'][$key]['filename']);
									}
								} elseif (isset($email['attachments'][$key]['name']) && !empty($email['attachments'][$key]['name'])) {
									$_at_name = trim($email['attachments'][$key]['name']);
								} elseif (isset($email['attachments'][$key]['filename']) && !empty($email['attachments'][$key]['filename'])) {
									$_at_name = trim($email['attachments'][$key]['filename']);
								}

								// Set the filename
								unset($email['attachments'][$key]['name']);
								$email['attachments'][$key]['filename'] = $_at_name;
							} else {
								$email['attachments'][$key]['data'] = '';
								$email['attachments'][$key]['filename'] = $_at_name;
							}
						} else {
							// Fallback: use name from attachment metadata
							$_at_name = isset($email['attachments'][$key]['name']) ? $email['attachments'][$key]['name'] :
									   (isset($email['attachments'][$key]['filename']) ? $email['attachments'][$key]['filename'] : $_at_name);
							unset($email['attachments'][$key]['name']);
							$email['attachments'][$key]['filename'] = $_at_name;
							$email['attachments'][$key]['data'] = '';
						}
					}
					// Add the attachments to data
					$data['attachments'] = $email['attachments'];
				} else {
					// No attachments
					$data['attachments'] = [];
				}

				// Check for To
				$data['to'] = [];
				if (isset($email['to']) && is_array($email['to'])) {
					foreach ($email['to'] as $to) {
						$data['to'][] = trim(preg_replace('/(.*)<(.*)>/', '\\2', $to));
					}
				}

				// Check for CC
				$data['cc'] = [];
				if (isset($email['cc']) && is_array($email['cc'])) {
					foreach ($email['cc'] as $cc) {
						$data['cc'][] = trim(preg_replace('/(.*)<(.*)>/', '\\2', $cc));
					}
				}

				if ('true' == hooks()->apply_filters('imap_fetch_from_email_by_reply_to_header', 'true')) {
					if (method_exists($imap, 'getReplyToAddresses')) {
						$replyTo = $imap->getReplyToAddresses($email['uid']);
					} else {
						$replyTo = $imap->getReplyToAddresses($email['uid']);
					}

					if (is_array($replyTo) && 1 === count($replyTo)) {
						$email['from'] = $replyTo[0];
					}
				}
				// Extract from email address
				$from_email = '';
				if (!empty($email['from'])) {
					$from_email = preg_replace('/(.*)<(.*)>/', '\\2', $email['from']);
					// If regex didn't match (no < > brackets), use the whole string
					if ($from_email == $email['from']) {
						$from_email = trim($email['from']);
					}
				}

				$decodedfrom = $from_email;

				// Skip if from email is empty
				if (empty($decodedfrom)) {
					continue;
				}

				$data['fromname'] = preg_replace('/(.*)<(.*)>/', '\\1', $email['from']);
				$data['fromname'] = trim(str_replace('"', '', $data['fromname']));

				// Decode sender's name if it's encoded with Base64
				if (preg_match('/\=\?UTF\-8\?B\?(.*?)\?=/i', $data['fromname'], $matches)) {
					$data['fromname'] = base64_decode($matches[1]);
				}

				$inbox = [];
				$inbox['from_email'] = $decodedfrom;
				$from_staff_id = get_staff_id_by_email(trim($decodedfrom));
				if ($from_staff_id) {
					$inbox['from_staff_id'] = $from_staff_id;
				}

				$from = $data['fromname'];
				$subject = $email['subject'];

				// Normalize sender name
				$fromname = preg_replace('/(.*)<(.*)>/', '\\1', $from);
				$fromname = trim(str_replace('"', '', $fromname));
				$decoded_fromname = decode_mime_header($fromname);

				// Normalize subject
				$decoded_subject = decode_mime_header($subject);

				$inbox['to'] = implode(',', $data['to']);
				$inbox['cc'] = implode(',', $data['cc']);
				$inbox['sender_name'] = $decoded_fromname;
				$inbox['subject'] = $decoded_subject;
				$inbox['body'] = $email['body'];
				$inbox['to_staff_id'] = $staff_id;
				$inbox['trash'] = 0; // Ensure emails are not marked as trash
				$inbox['read'] = 0; // Mark as unread by default
				$inbox['date_received'] = date('Y-m-d H:i:s');
				$inbox['folder'] = $system_folder_type;

				// Store IMAP UID (for syncing read status)
				if ($CI->db->field_exists('uid', db_prefix() . 'mail_inbox') && isset($email['uid'])) {
					$inbox['uid'] = $email['uid'];
				}

				// Store IMAP folder name (only if column exists)
				if ($CI->db->field_exists('imap_folder', db_prefix() . 'mail_inbox')) {
					$inbox['imap_folder'] = $imap_folder_name;
				}

				// Store threading information (only if columns exist AND threading is enabled)
				if ($CI->db->field_exists('message_id', db_prefix() . 'mail_inbox') && ('1' == get_option('mailbox_threading_enabled'))) {
					$inbox['message_id'] = isset($email['message_id']) ? $email['message_id'] : null;
					$inbox['in_reply_to'] = isset($email['in_reply_to']) ? $email['in_reply_to'] : null;
					$inbox['references'] = isset($email['references']) ? $email['references'] : null;
					if (function_exists('generate_thread_id')) {
						$inbox['thread_id'] = generate_thread_id(
							$inbox['message_id'] ?? '',
							$inbox['in_reply_to'] ?? '',
							$inbox['references'] ?? '',
							$decoded_subject,
							$staff_id
						);
					}
				}

				$CI->db->insert(db_prefix().'mail_inbox', $inbox);
				$inbox_id = $CI->db->insert_id();

				if ($inbox_id) {
					$inbox_email_ids[] = $inbox_id;

					// Process email rules (use process_mailbox_rules which is the correct function name)
					if (function_exists('process_mailbox_rules')) {
						try {
							process_mailbox_rules($inbox_id, $staff_id, 'inbox');
						} catch (Exception $e) {
							// Silent fail for rules processing
						}
					} elseif (function_exists('process_email_rules')) {
						// Fallback to old function name for backward compatibility
						$email_rule_data = [
							'from_email' => $decodedfrom,
							'from' => $from,
							'subject' => $decoded_subject,
							'body' => $email['body'],
							'to' => $inbox['to'],
						];
						try {
							process_email_rules($inbox_id, $email_rule_data, $staff_id);
						} catch (Exception $e) {
							// Silent fail for rules processing
						}
					}

					// Process attachments only if email was inserted successfully
					$path     = MAILBOX_MODULE_UPLOAD_FOLDER.'/inbox/'.$inbox_id.'/';

					// Create directory if it does not exist
					if (!file_exists($path)) {
						if (!mkdir($path, 0755, true)) {
							continue; // Skip this email if directory creation fails
						}
						$fp = fopen($path.'index.html', 'w');
						if ($fp) {
							fwrite($fp, '');
							fclose($fp);
						}
					}

					$attachments_saved = 0; // Track successfully saved attachments
					foreach ($data['attachments'] as $attachment) {
						if (empty($attachment['data'])) {
							continue;
						}

						$filename = $attachment['filename'];

						// Sanitize filename - remove dangerous characters but keep extension
						$filenameparts = explode('.', $filename);
						if (count($filenameparts) > 1) {
							// Has extension
							$extension = strtolower(end($filenameparts));
							$filename_base = implode('.', array_slice($filenameparts, 0, -1)); // Join all parts except last
							$filename_base = trim(preg_replace('/[^a-zA-Z0-9-_. ]/', '', $filename_base));
							if (empty($filename_base)) {
								$filename_base = 'attachment';
							}
							$filename = $filename_base . '.' . $extension;
						} else {
							// No extension - sanitize the whole filename
							$filename = trim(preg_replace('/[^a-zA-Z0-9-_. ]/', '', $filename));
							if (empty($filename)) {
								$filename = 'attachment';
							}
						}

						// Use unique_filename to handle duplicates
						$filename = unique_filename($path, $filename);
						$filepath = $path . $filename;

						// Write attachment content (binary safe)
						$bytes_written = file_put_contents($filepath, $attachment['data'], LOCK_EX);

						if ($bytes_written === false) {
							continue;
						}

						// Verify file was written and is readable
						if (!file_exists($filepath) || filesize($filepath) == 0) {
							continue;
						}

						$matt               = [];
						$matt['mail_id']    = $inbox_id;
						$matt['type']       = 'inbox';
						$matt['file_name']  = $filename;
						$matt['file_type']  = get_mime_by_extension($filename);
						$CI->db->insert(db_prefix().'mail_attachment', $matt);

						if ($CI->db->affected_rows() > 0) {
							$attachments_saved++;
						}
					}

					// Update has_attachment flag if at least one attachment was saved
					if ($attachments_saved > 0) {
						$CI->db->where('id', $inbox_id);
						$CI->db->update(db_prefix().'mail_inbox', [
							'has_attachment' => 1,
						]);
					}

					// REMOVED: Auto-mark as read on import
					// Now controlled by per-staff sync_read_status_to_server setting
					// Emails are only marked as read on server when user views them in Perfex (if setting enabled)
				}
				} catch (Exception $e) {
					// Log error and continue with next email
					log_activity('Error processing email for ' . $staff_email . ': ' . $e->getMessage() . ' (File: ' . $e->getFile() . ', Line: ' . $e->getLine() . ')', null);
					continue;
				}
					}

					// Update last UID for this folder (for ImapService only)
					if ($usingImapService && $highest_uid > 0 && function_exists('update_mailbox_folder_last_uid')) {
						try {
							update_mailbox_folder_last_uid($staff_id, $imap_folder_name, $highest_uid);
						} catch (Exception $e) {
							// Silent fail for UID update
						}
					}
				} catch (Exception $e) {
					// Log error and continue with next folder
					log_activity('Error processing folder ' . $imap_folder_name . ' for ' . $staff_email . ': ' . $e->getMessage() . ' (File: ' . $e->getFile() . ', Line: ' . $e->getLine() . ')', null);
					continue;
				}
			}
		}
	}

	return false;
}

function send_schedule_emails()
{
    $CI = &get_instance();

    // CRITICAL: Reset query builder to clear any leftover state
    $CI->db->reset_query();

    // Query scheduled emails
    $CI->db->select('*');
    $CI->db->from(db_prefix() . 'mail_outbox');
    $CI->db->where('scheduled_status', 'Scheduled');
    $CI->db->where('scheduled_at <=', date('Y-m-d H:i:s'));
    $scheduled_outboxes = $CI->db->get()->result_array();

    foreach ($scheduled_outboxes as $scheduled_outbox) {
        // CRITICAL: Reset query builder before each staff query
        $CI->db->reset_query();

        // Get staff information with OAuth credentials
        $CI->db->select('email, auth_method, oauth_provider, oauth_access_token, oauth_refresh_token, oauth_token_expires_at, oauth_client_id, oauth_client_secret, oauth_tenant_id');
        $CI->db->from(db_prefix().'staff');
        $CI->db->where('staffid', $scheduled_outbox['sender_staff_id']);
        $staff = $CI->db->get()->row_array();

        if (!$staff) {
            continue; // Skip if staff not found
        }

        // Prepare recipients
        $to = str_replace(';', ',', $scheduled_outbox['to']);
        $cc = isset($scheduled_outbox['cc']) && strlen($scheduled_outbox['cc']) > 0 ? $scheduled_outbox['cc'] : '';

        // Check if staff has OAuth configured for sending
        $oauth_send_success = false;
        if (isset($staff['auth_method']) && $staff['auth_method'] === 'oauth2' && !empty($staff['oauth_provider'])) {
            // Try OAuth sending
            $CI->load->model('mailbox/mailbox_model');
            try {
                $oauth_send_success = $CI->mailbox_model->send_email_via_oauth(
                    $staff,
                    $to,
                    $cc,
                    $scheduled_outbox['subject'],
                    $scheduled_outbox['body'],
                    [], // Attachments handled separately for scheduled emails
                    null, // message_id
                    null, // in_reply_to
                    null  // references
                );
            } catch (Exception $e) {
                log_activity('Scheduled email OAuth send failed: ' . $e->getMessage());
                $oauth_send_success = false;
            }
        }

        // Fallback to standard SMTP if OAuth not configured or failed
        if (!$oauth_send_success) {
            $CI->email->initialize();
            $CI->load->library('email');
            $CI->email->clear(true);
            $CI->email->from($staff['email'], $scheduled_outbox['sender_name']);
            $CI->email->to($to);
            if (!empty($cc)) {
                $CI->email->cc($cc);
            }
            $CI->email->subject($scheduled_outbox['subject']);
            $CI->email->message($scheduled_outbox['body']);
            $outobx_attach_dir = module_dir_url(MAILBOX_MODULE).'uploads/outbox/'.$scheduled_outbox['id'];
            if (file_exists($outobx_attach_dir)) {
                $outbox_files = scandir($outobx_attach_dir);
                $outbox_files = array_diff($outbox_files, array(".", ".."));
                foreach ($outbox_files as $outbox_file) {
                    $CI->email->attach($outobx_attach_dir.'/'.$outbox_file);
                }
            }
            $CI->email->send(true);
        }

        // CRITICAL: Reset query builder before update
        $CI->db->reset_query();

        // Update scheduled status
        $CI->db->where('id', $scheduled_outbox['id']);
        $CI->db->update(db_prefix().'mail_outbox', ['scheduled_status' => 'Sent']);

        log_activity('Schedule Email Sent - ID: ' . $scheduled_outbox['id'] . ' To: ' . $scheduled_outbox['to']);
    }
}

/**
 * Load the module helper.
 */
$CI = &get_instance();
$CI->load->helper(MAILBOX_MODULE.'/mailbox');

/*
 * Register the activation mailbox
 */
register_activation_hook(MAILBOX_MODULE, 'mailbox_activation_hook');

/**
 * The activation function.
 */
function mailbox_activation_hook()
{
    $CI = &get_instance();
    require_once __DIR__.'/install.php';
}

/*
 * Register mailbox language files
 */
register_language_files(MAILBOX_MODULE, [MAILBOX_MODULE]);


hooks()->add_action('app_init', MAILBOX_MODULE.'_actLib');
function mailbox_actLib()
{
  
}

hooks()->add_action('pre_activate_module', MAILBOX_MODULE.'_sidecheck');
function mailbox_sidecheck($module_name)
{
    if (MAILBOX_MODULE == $module_name['system_name']) {

    }
}

hooks()->add_action('pre_deactivate_module', MAILBOX_MODULE.'_deregister');
function mailbox_deregister($module_name)
{
    if (MAILBOX_MODULE == $module_name['system_name']) {

    }
}

function mailbox_after_lead_lead_tabs(){
   echo '<li role="presentation">
            <a href="#conversation" aria-controls="conversation" role="tab" data-toggle="tab">
                ' . _l("conversation") . '
            </a>
        </li>';
}

function mailbox_after_lead_tabs_content($data){
    if ($data) {
        $CI = &get_instance();
        $id = $data->id;
        $data = array();
        $CI->db->select('*');
        $CI->db->from('mail_conversation');
        $CI->db->where('lead_id', $id);
        $result = $CI->db->get()->result_array();
        $getdata = [];
        foreach ($result as $key => $value) {
        if ($value['inbox_id']) {
                $CI->db->select('*');
                $CI->db->from('mail_inbox');
                $CI->db->join('mail_conversation', 'mail_inbox.id = mail_conversation.inbox_id');
                $CI->db->where('mail_inbox.id', $value['inbox_id']);
                $result_array = $CI->db->get()->result_array();
                if ($result_array) {
                    $getdata[] = $result_array[0];
                }
            } else {
                $CI->db->select('*');
                $CI->db->from('mail_outbox');
                $CI->db->join('mail_conversation', 'mail_outbox.id = mail_conversation.outbox_id');
                $CI->db->where('mail_outbox.id', $value['outbox_id']);
                $result_array = $CI->db->get()->result_array();
                if ($result_array) {
                    $getdata[] = $result_array[0];
                }
            }
        }
        $data['conversation']  = $getdata;
        $data['module_dir_url'] = module_dir_url(MAILBOX_MODULE);
        $CI = &get_instance();
        echo $CI->load->view('mailbox/conversation', $data, true);
    }
}

function mailbox_supported_until() {
    if (get_option('extra_support_notice') == 0) {
        return;
    } else {
        $supported_until = get_option(MAILBOX_MODULE.'_supported_until');
        if (empty($supported_until)) {
            return;
        }
		$date_only = substr($supported_until, 0, 10);
		$supported_until_timestamp = strtotime($date_only);
		$current_date_timestamp = time();
		if ($supported_until_timestamp < ($current_date_timestamp - (6 * 30 * 24 * 60 * 60))) {
            echo '<div class="supported_until alert alert-warning" style="font-size: 16px; background-color: #fff3cd; border-color: #ffeeba; color: #856404;
                position: fixed; top: 50px; left: 50%; padding: 20px; transform: translateX(-50%); z-index: 9999; width: 90%; max-width: 600px; box-shadow: rgba(0, 0, 0, 0.25) 0px 54px 55px, rgba(0, 0, 0, 0.12) 0px -12px 30px, rgba(0, 0, 0, 0.12) 0px 4px 6px, rgba(0, 0, 0, 0.17) 0px 12px 13px, rgba(0, 0, 0, 0.09) 0px -3px 5px;">
                    <img style="max-width:100px;" src="https://themesic.com/wp-content/uploads/2023/07/cropped-logo-with-text-minus.png"><br><br>
                    <p>⚠️ The support period for one of your modules seems over.<br><br>We offer an alternative way to receive <strong>free support</strong> for potential issues,<br>simply by rating our product on <img style="max-width:80px;" src="https://themesic.com/wp-content/plugins/fast-plugin/assets/images/envato.svg">. <a href="https://1.envato.market/themesic" target="_blank" style="text-decoration:underline !important;"><strong> Click here to do that</strong></a> 👈</p><br>
                    <p>Your feedback help us continue developing and improving the product!</p>
                    <br /><br />
                    <a href="?dismiss=true" class="alert-link" style="text-decoration:underline !important;">Okay, thanks for the notice</a> ✔️
                </div></center>';
		}
    }
}

// Check for the dismiss URL and update the option
if (isset($_GET['dismiss']) && $_GET['dismiss'] === 'true') {
    update_option('extra_support_notice', 0); // Dismiss the notice
    // Redirect to clear the URL parameter and avoid it being triggered again
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

hooks()->add_action('app_admin_head', 'mailbox_supported_until');

function mailbox_hide_support_extension() {
    echo "<script>
        jQuery(document).ready(function($) {
            // Get all elements with class 'supported_until'
            var divs = $('.supported_until');
            console.log('Total .supported_until divs:', divs.length); // Log how many divs are rendered

            // If more than one div, hide all except the first
            if (divs.length > 1) {
                divs.slice(1).hide(); // Hide all but the first one
            }
        });
    </script>";
}

hooks()->add_action('app_admin_footer', 'mailbox_hide_support_extension');

function customer_profile_tabs($tabs) {
    $tabs['mail'] = [
        'slug' => 'email',
        'name' => 'Emails',
        'icon' => 'fa fa-envelope',
        'view' => 'mailbox/mailbox_clients',
        'position' => '150',
        'badge' => [],
        'href' => 'admin/mailbox/client_mails',
        'children' => [],
    ];

    return $tabs;
}
