/**
 * @author    Adilis <support@adilis.fr>
 * @copyright 2024 SAS Adilis
 * @license   http://www.adilis.fr
 */

$(function () {
    const $selectCountryClone = $('#id_country').clone();
    const $idZoneSelect = $('#id_zone');
    const $ruleTypeSelect = $('#rule_type');
    const $impactAmountInput = $('#impact_amount');
    const $impactPercentInput = $('#impact_percent');

    function updateCountryOptions(selectedZone) {
        const $selectedCountry = $('#id_country');
        if (selectedZone === 0) {
            $selectedCountry.closest('.form-group').hide().end().val(0);
        } else {
            const valueBefore = parseInt($selectedCountry.val());
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
            $selectedCountry.html(options).closest('.form-group').show();
            $selectedCountry.val(valueBeforeExists ? valueBefore : 0);
        }
    }

    function toggleImpactAmount() {
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


    }

    $idZoneSelect.on('change', function () {
        updateCountryOptions(parseInt($(this).val()) || 0);
    }).trigger('change');

    $ruleTypeSelect.on('change', toggleImpactAmount).trigger('change');
});
