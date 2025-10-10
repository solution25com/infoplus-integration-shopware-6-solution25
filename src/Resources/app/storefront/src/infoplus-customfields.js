document.addEventListener('DOMContentLoaded', function () {
    var addToCartForm = document.querySelector('form[action*="checkout/cart"]');
    var infoplusForm = document.getElementById('infoplus-customfields-form');
    if (!addToCartForm || !infoplusForm) return;

    addToCartForm.addEventListener('submit', function () {
        var inputs = infoplusForm.querySelectorAll('input, select, textarea');
        inputs.forEach(function (input) {
            var name = input.name;
            if (!name) return;
            var value;
            if (input.type === 'checkbox') {
                value = input.checked ? '1' : '0';
            } else {
                value = input.value;
            }
            var hidden = addToCartForm.querySelector('input[type="hidden"][name="' + name + '"]');
            if (!hidden) {
                hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = name;
                addToCartForm.appendChild(hidden);
            }
            hidden.value = value;
        });
    });
});
