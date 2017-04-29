// Making sure that console.log does not throw errors on Firefox + IE etc.
if (typeof console == "undefined") var console = { log: function() {} };

if (typeof AccountLinker == "undefined") var AccountLinker = {};

AccountLinker.Control = {

	"url" : "https://test-login.terena.org/wayf/module.php/accountLinker/search.php",

	"searchboxInit" : function() {

	}

};


$(function() {
	AccountLinker.Control.searchboxInit();
});