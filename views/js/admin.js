$(document).ready(() => {
    const $selectCountryClone = $('#id_country').clone();
    const $idZoneSelect = $('#id_zone');

    $idZoneSelect.on('change', function () {
        const selectedZone = parseInt($(this).val()) || 0;
        const selectedCountry = $('#id_country');
        if (selectedZone === 0) {
            selectedCountry.closest('.form-group').hide();
            selectedCountry.val(0);
        } else {
            const valueBefore = parseInt(selectedCountry.val());
            let valueBeforeExists = false;
            selectedCountry.html('');
            $selectCountryClone.children().each(function () {
                const $this = $(this);
                const idZone = parseInt($this.attr('data-id-zone')) || 0;
                if (idZone === -1 || idZone === selectedZone) {
                    selectedCountry.append($(this).clone());
                    if (parseInt($this.val()) === valueBefore) {
                        valueBeforeExists = true;
                    }
                }
            });
            selectedCountry.closest('.form-group').show();
            selectedCountry.val(valueBeforeExists ? valueBefore : 0);
        }
    });

    $idZoneSelect.trigger('change');

    const $ruleTypeSelect = $('#rule_type');
    const $impactAmountInput = $('#impact_amount');
    $ruleTypeSelect.on('change', function () {
        if (parseInt($(this).val()) === 1) {
            $impactAmountInput.closest('.form-group').show();
        } else {
            $impactAmountInput.closest('.form-group').hide();
            $impactAmountInput.val(0);
        }
    });

    $ruleTypeSelect.trigger('change');
});