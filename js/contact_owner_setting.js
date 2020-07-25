jQuery(document).ready( function() {

	new GFOwnerSetting();

} );


function GFOwnerSetting() {
	var $ = jQuery;

	this.init = function() {

		this.initOwner();

		new GFConditionsSetting();
	}

	this.initOwner = function() {
		var t = this;

		$('#contact_owner0, #contact_owner1, #contact_owner2').on('change', function() {
			t.toggleOwner( true );
		});

		this.toggleOwner( false );
	}

	this.toggleOwner = function( doSlide ) {

		$('#gaddon-setting-row-contact_owner_conditional, #gaddon-setting-row-contact_owner_select').hide();

		var radioValue = $("input[name='_gaddon_setting_contact_owner']:checked").val();

		if( radioValue != 'none' ) {

			if ( $('.contact_owner_section').is(':hidden') ) {
				if ( doSlide ) {
					$('.contact_owner_section').slideDown();
				} else {
					$('.contact_owner_section').show();
				}

			}

			$('#gaddon-setting-row-contact_owner_' + radioValue).show();
		} else {
			if ( doSlide ) {
				$('.contact_owner_section').slideUp();
			} else {
				$('.contact_owner_section').hide();
			}
		}
	}

	//Initializes this class
	this.init();
}

function GFConditionsSetting() {
    var $ = jQuery;

	//Instantiating conditions repeater
	this.$element = jQuery('#gform_conditions_setting');
	this.fieldId = 'conditions';
	this.fieldName = '_gaddon_setting_conditions';
	this.options = null;

    this.init = function() {

    	var t = this;

		this.options = {
			fieldName: this.fieldName,
			fieldId: this.fieldId,
			fields: gform_hubspot_owner_settings_strings['fields'],// [{'id': '1', 'label':'Name'}, {'id':'2', 'label': 'Email'} ],// gf_routing_setting_strings['fields'],
			owners: gform_hubspot_owner_settings_strings['owners'],
			imagesURL: gf_vars.baseUrl + "/images",
			items: this.getItems(),
			operatorStrings: {"is":"is","isnot":"isNot", ">":"greaterThan", "<":"lessThan", "contains":"contains", "starts_with":"startsWith", "ends_with":"endsWith"},
		};


		var routingsMarkup, headerMarkup;
		headerMarkup = '<thead><tr><th>{0}</th><th colspan="3">{1}</th></tr></thead>'.format( gform_hubspot_owner_settings_strings['assign_to'], gform_hubspot_owner_settings_strings['condition'] );
		routingsMarkup = '<table class="gform-routings">{0}<tbody class="repeater">{1}</tbody></table>'.format(headerMarkup, this.getNewRow());

		var $routings = $(routingsMarkup);

		$routings.find('.repeater').repeater({

			limit             : 0,
			items             : this.options.items,
			addButtonMarkup   : '<i class="gficon-add"></i>',
			removeButtonMarkup: '<i class="gficon-subtract"></i>',
			callbacks         : {
				save     : function (obj, data) {
					$('#' + t.options.fieldId).val($.toJSON(data));
				},
				beforeAdd: function (obj, $elem, item) {
					if ( item.owner ) {
						$elem.find('.gform-routing-owners').val(item.owner);
					}

					var $field = $elem.find('.gform-routing-field').first();
					$field.value = item.fieldId;
					t.changeField($field);

					var $operator = $elem.find('.gform-routing-operator').first();
					$operator.value = item.operator;

					t.changeOperator($operator);

					var $value = $elem.find('.gform-routing-value');
					$value.val(item.value);

				},
			}
		})
			.on('change', '.gform-routing-field', function (e) {
				t.changeField(this);
			})
			.on('change', '.gform-routing-operator', function () {
				t.changeOperator(this);
			});


		this.$element.append($routings);
	}

	this.getNewRow = function () {
		var r = [];

		r.push( '<td>{0}</td>'.format( this.getOwners() ) );
		r.push( '<td>{0}</td>'.format( this.getFields() ) );
		r.push( '<td>{0}</td>'.format( this.getOperators( this.options.fields[0] ) ) );
		r.push( '<td>{0}</td>'.format( this.getValues() ) );
		r.push( '<td>{buttons}</td>' );

		return '<tr class="gform-routing-row">{0}</tr>'.format( r.join('') );
	},

	this.getOwners = function () {

        var i, n, account,
			owners = this.options.owners,
			str = '<select class="gform-routing-owners owner_{i}">';

		for (i = 0; i < owners.length; i++) {
            str += '<option value="{0}">{1}</option>'.format( owners[i].value, owners[i].label );
		}

		str += "</select>";
		return str;
	},

	this.getFields = function () {
		var i, j, key, val, label, groupLabel, options, numRows,
			select = [],
			settings = this.options.fields;
		select.push('<select class="gform-routing-field fieldId_{i}" >');
		for (i = 0; i < settings.length; i++) {
			key = settings[i].key;
			if (settings[i].group) {
				groupLabel = settings[i].text;
				numRows = settings[i].filters.length;
				options = [];
				for (j = 0; j < numRows; j++) {
					label = settings[i].filters[j].text;
					val = settings[i].filters[j].key;
					options.push('<option value="{0}">{1}</option>'.format(val, label));
				}
				select.push('<optgroup label="{0}">{1}</optgroup>'.format(groupLabel, options.join('')));
			} else {
				label = settings[i].text;
				select.push('<option value="{0}">{1}</option>'.format(key, label));
			}

		}
		select.push("</select>");
		return select.join('');
	},

	this.changeOperator = function  (operatorSelect) {
		var $select = $(operatorSelect),
			$buttons = $select.closest('tr').find('.repeater-buttons');
		var index = $buttons.find('.add-item ').data('index');
		var $fieldSelect = $select.closest('tr').find('.gform-routing-field');
		var filter = this.getFilter($fieldSelect.value);
		if (filter) {
			$select.closest('tr').find(".gform-routing-value").replaceWith(this.getValues(filter, operatorSelect.value, index));
		}
	},

	this.changeField = function  (fieldSelect) {
		var filter = this.getFilter(fieldSelect.value);
		if (filter) {
			var $select = $(fieldSelect),
				$buttons = $select.closest('tr').find('.repeater-buttons');
			var index = $buttons.find('.add-item ').data('index');
			$select.closest('tr').find(".gform-routing-value").replaceWith(this.getValues(filter, null, index));
			$select.closest('tr').find(".gform-filter-type").val(filter.type).change();
			var $newOperators = $(this.getOperators(filter, index));
			$select.closest('tr').find(".gform-routing-operator").replaceWith($newOperators);
			$select.closest('tr').find(".gform-routing-operator").change();
		}
	},

	this.getOperators = function (filter, index) {
		if ( typeof index == 'undefined' || index === null ){
			index = '{i}';
		}
		var i, operator,
			operatorStrings = this.options.operatorStrings,
			str = '<select class="gform-routing-operator operator_{0}">'.format(index);

		if (filter) {
			for (i = 0; i < filter.operators.length; i++) {
				operator = filter.operators[i];
				str += '<option value="{0}">{1}</option>'.format(operator, gf_vars[operatorStrings[operator]] );
			}
		}
		str += "</select>";
		return str;
	},

	this.getValues = function  (filter, selectedOperator, index) {
		var i, val, text, str, options = "";

		if ( typeof index == 'undefined' || index === null ){
			index = '{i}';
		}

		if ( filter && filter.values && selectedOperator != 'contains' ) {
			for (i = 0; i < filter.values.length; i++) {
				val = filter.values[i].value;
				text = filter.values[i].text;
				options += '<option value="{0}">{1}</option>'.format(val, text);
			}
			str = '<select class="gform-routing-value value_{0}">{1}</select>'.format(index, options);
		} else {
			str = '<input type="text" value="" class="gform-routing-value value_{0}" />'.format(index);
		}

		return str;
	},

	this.getFilter = function  (key) {
		var fields = this.options.fields;
		if (!key)
			return;
		for (var i = 0; i < fields.length; i++) {
			if (key == fields[i].key)
				return fields[i];
			if (fields[i].group) {
				for (var j = 0; j < fields[i].filters.length; j++) {
					if (key == fields[i].filters[j].key)
						return fields[i].filters[j];
				}
			}

		}
	},

	this.selected = function (selected, current){
		return selected == current ? 'selected="selected"' : "";
	}

	this.getItems = function () {
		var json = $('#' + this.fieldId ).val();
		var default_items = [ {owner: '', fieldId: '0', operator: 'is', value: ''} ];
		var items = json ? $.parseJSON(json) : default_items;
		return items;
	}

	this.init();

	String.prototype.format = function () {
		var args = arguments;
		return this.replace(/{(\d+)}/g, function (match, number) {
			return typeof args[number] != 'undefined' ? args[number] : match;
		});
	};

}

