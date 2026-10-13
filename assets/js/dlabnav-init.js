
"use strict"

var dlabSettingsOptions = {};

function getUrlParams(dParam) 
	{
		var dPageURL = window.location.search.substring(1),
			dURLVariables = dPageURL.split('&'),
			dParameterName,
			i;

		for (i = 0; i < dURLVariables.length; i++) {
			dParameterName = dURLVariables[i].split('=');

			if (dParameterName[0] === dParam) {
				return dParameterName[1] === undefined ? true : decodeURIComponent(dParameterName[1]);
			}
		}
	}

(function($) {
	
	"use strict"
	
	var initialVersion = $('body').attr('data-theme-version') || (function() {
		var match = document.cookie.match(new RegExp('(^| )version=([^;]+)'));
		return match ? match[2] : 'light';
	})() || 'light';

	dlabSettingsOptions = {
			typography: "poppins",
			version: initialVersion,
			layout: "vertical",
			primary: "color_1",
			headerBg: "color_1",
			navheaderBg: "color_2",
			sidebarBg: "color_2",
			sidebarStyle: "full",
			sidebarPosition: "fixed",
			headerPosition: "fixed",
			containerLayout: "full",
		};
		
	new dlabSettings(dlabSettingsOptions); 

	jQuery(window).on('resize',function(){
        /*Check container layout on resize */
        dlabSettingsOptions.containerLayout = $('#container_layout').val();
        dlabSettingsOptions.version = $('body').attr('data-theme-version') || 'light';
        /*Check container layout on resize END */
        
		new dlabSettings(dlabSettingsOptions); 
	});
	
})(jQuery);