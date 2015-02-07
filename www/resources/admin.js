// Making sure that console.log does not throw errors on Firefox + IE etc.
if (typeof console == "undefined") var console = { log: function() {} };

if (typeof AccountLinker == "undefined") var AccountLinker = {};

AccountLinker.Control = {

	"url" : "https://test-login.terena.org/wayf/module.php/accountLinker/search.php",

	"searchboxInit" : function() {
		var that = this;
		$("form.searchform input").change(function(e){
			that.load(e.target);
		});
		$("a.reset").click(function(e){
			e.preventDefault();
			that.reset(e.target);
		});

	},

	"reset": function(elm) {
		var that = this;
		var formElm = $(elm).parent().parent().parent();

		$.ajax({
			url: that.url,
			data: {"reset": true, "session":formElm.attr('id')},
			success: function(){
				that.resetFormFields(formElm);
				formElm.next().empty();
			}
		});

	},

	"load": function(elm) {
		var that = this;
		var elmId = $(elm).parent().parent().attr('id');

		//var url = this.parent.Utils.options.get('url');
		var parameters = {
			"type": elm.name,
			"val": elm.value,
			"session": elmId
		};

		$.getJSON(that.url, parameters, function(data) {
			that.data = data;
			that.postLoad(elm, (elmId != 'c')?false:true);
		});
	},

	"postLoad": function(elm, search) {
		var that = this;
		if (!this.data) return;

		//number of hits
		//Object.keys(this.data).length

		var items = [];
	},

	"resetFormFields": function(elm) {
		elm.find(':input').each(function() {
		    switch(this.type) {
		        case 'password':
		        case 'select-multiple':
		        case 'select-one':
		        case 'text':
		        case 'textarea':
		            $(this).val('');
		            break;
		        case 'checkbox':
		        case 'radio':
		            this.checked = false;
		    }
		});
	}

};


$(function() {
	AccountLinker.Control.searchboxInit();
});