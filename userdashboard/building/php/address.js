const addressList = [
    "Barangay Abuanan, Bago City, Negros Occidental, Western Visayas",
    "Barangay Alianza, Bago City, Negros Occidental, Western Visayas",
    "Barangay Atipuluan, Bago City, Negros Occidental, Western Visayas",
    "Barangay Bacong, Bago City, Negros Occidental, Western Visayas",
    "Barangay Bagroy, Bago City, Negros Occidental, Western Visayas",
    "Barangay Balingasag, Bago City, Negros Occidental, Western Visayas",
    "Barangay Binubuhan, Bago City, Negros Occidental, Western Visayas",
    "Barangay Busay, Bago City, Negros Occidental, Western Visayas",
    "Barangay Calumangan, Bago City, Negros Occidental, Western Visayas",
    "Barangay Caridad, Bago City, Negros Occidental, Western Visayas",
    "Barangay Don Jorge Araneta, Bago City, Negros Occidental, Western Visayas",
    "Barangay Dulao, Bago City, Negros Occidental, Western Visayas",
    "Barangay Ilijan, Bago City, Negros Occidental, Western Visayas",
    "Barangay Lag-asan, Bago City, Negros Occidental, Western Visayas",
    "Barangay Ma-ao, Bago City, Negros Occidental, Western Visayas",
    "Barangay Mailum, Bago City, Negros Occidental, Western Visayas",
    "Barangay Malingin, Bago City, Negros Occidental, Western Visayas",
    "Barangay Napoles, Bago City, Negros Occidental, Western Visayas",
    "Barangay Pacol, Bago City, Negros Occidental, Western Visayas",
    "Barangay Poblacion, Bago City, Negros Occidental, Western Visayas",
    "Barangay Sagasa, Bago City, Negros Occidental, Western Visayas",
    "Barangay Sampinit, Bago City, Negros Occidental, Western Visayas",
    "Barangay Tabunan, Bago City, Negros Occidental, Western Visayas",
    "Barangay Taloc, Bago City, Negros Occidental, Western Visayas"
];

$(function () {
    $("#address").autocomplete({
        source: addressList,
        minLength: 2
    });

    // Optional: Form validation feedback
    $('#addressForm').on('submit', function (e) {
        const input = $('#address');
        if (!input.val()) {
            input.addClass('is-invalid');
            e.preventDefault();
        } else {
            input.removeClass('is-invalid');
        }
    });
});