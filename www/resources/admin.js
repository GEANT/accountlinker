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
		items.push('<div class="result_container">');
		$.each(this.data, function(i, item) {
			// group block
			items.push('<div class="infobox outerbox">');			
			items.push('<div class="grnav">group '+i+'</div>');

				// account block
				$.each(item, function(j, val) {
					items.push('<div class="infobox">');
					// items.push('<div>acc: '+j+'</div>')
					items.push('<h2>'+val.entityid_name+'</h2>');
					// attributes
					items.push('<ul>');
					$.each(val.attributes, function(k, v) {
					    items.push('<li>'+k+':'+v+'</li>');
					});					
					items.push('</ul>');
					if (!search) {
						items.push('<a href="#">Link this account to parent group</a>');
					}
					items.push('</div>');
				});

			if (search) {
				items.push('<a href="#" class="link">Add account to this group</a>');
			}
			items.push('</div>');
		});
		items.push('</div>');
		var frm = $(elm).parent().parent();
		//clear element
		frm.next('div.result_container').remove();
		frm.after( items.join('') );
		
		$('div.outerbox').each(function(i, elm){
		
		});
		
		that.addLinkHandler();

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