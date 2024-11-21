/**
 * @author    Adilis <support@adilis.fr>
 * @copyright 2024 SAS Adilis
 * @license   http://www.adilis.fr
 */

$(function () {
    const $selectCountryClone = $('#id_country').clone();
    const $idZoneSelect = $('#id_zone');
    const $idCountrySelect = $('#id_country');
    const $ruleTypeSelect = $('#rule_type');
    const $postcodeListTextarea = $('#postcode_list');
    const $impactAmountInput = $('#impact_amount');
    const $impactPercentInput = $('#impact_percent');
    const $newAmountInput = $('#new_amount');
    const $customerFilterInput = $('#customer_filter');
    const $customerIdInput = $('#id_customer');

    function updateCountryOptions(selectedZone) {
        if (selectedZone === 0) {
            $idCountrySelect.closest('.form-group').hide().end().val(0);
        } else {
            const valueBefore = parseInt($idCountrySelect.val());
            let valueBeforeExists = false;
            const options = $selectCountryClone.children().filter(function () {
                const idZone = parseInt($(this).attr('data-id-zone')) || 0;
                if (idZone === -1 || idZone === selectedZone) {
                    if (parseInt($(this).val()) === valueBefore) {
                        valueBeforeExists = true;
                    }
                    return true;
                }
                return false;
            });
            $idCountrySelect.html(options).closest('.form-group').show();
            $idCountrySelect.val(valueBeforeExists ? valueBefore : 0);
        }
    }

    function toggleValueField() {
        const value = parseInt($(this).val()) || 0;
        if (value === 1) {
            $impactAmountInput.closest('.form-group').show();
        } else {
            $impactAmountInput.closest('.form-group').hide();
            $impactAmountInput.val(0);
        }

        if (value === 3) {
            $impactPercentInput.closest('.form-group').show();
        } else {
            $impactPercentInput.closest('.form-group').hide();
            $impactPercentInput.val(0);
        }

        if (value === 5) {
            $newAmountInput.closest('.form-group').show();
        } else {
            $newAmountInput.closest('.form-group').hide();
            $newAmountInput.val(0);
        }


    }

    $idZoneSelect.on('change', function () {
        updateCountryOptions(parseInt($(this).val()) || 0);
    }).trigger('change');

    $idCountrySelect.on('change', function () {
        const selectIdCountry = parseInt($(this).val()) || 0;
        if (selectIdCountry === 0) {
            $postcodeListTextarea.closest('.form-group').hide();
        } else {
            $postcodeListTextarea.closest('.form-group').show();
        }
    }).trigger('change');

    $ruleTypeSelect.on('change', toggleValueField).trigger('change');

    $('#shipping_rule_form').submit((e) => {
        if ($customerFilterInput.val() === '') {
            $customerIdInput.val('0');
        }

        if ((parseInt($($idCountrySelect).val()) || 0) === 0) {
            $postcodeListTextarea.val('');
        }
    });

    $customerFilterInput
        .autocomplete(
            'index.php', {
                minChars: 2,
                max: 50,
                width: 500,
                selectFirst: false,
                scroll: false,
                dataType: 'json',
                formatItem(data, i, max, value, term) {
                    return value;
                },
                parse(data) {
                    const mytab = new Array();

                    for (let i = 0; i < data.length; i++) {
                        mytab[mytab.length] = {
                            data: data[i],
                            value: (data[i].shop_name ? `${data[i].cname} (${data[i].email}) [${data[i].shop_name}]` : `${data[i].cname} (${data[i].email})`),
                        };
                    }

                    return mytab;
                },
                extraParams: {
                    ajax: 1,
                    controller: 'AdminCartRules',
                    token: adminCartRulesToken,
                    customerFilter: 1,
                },
            },
        )
        .result((event, data, formatted) => {
            $customerIdInput.val(data.id_customer);
            $customerFilterInput.val(`${data.cname} (${data.email})`);
        });
});

