function FormatUSNumeric(value) {
    // PERBAIKAN: Ubah 'value' menjadi String dulu, (value || 0) untuk menangani null
    const stringValue = String(value || 0); 

    const number = parseFloat(stringValue.replace(/[^0-9.-]/g, ''));
    if (isNaN(number)) return '0.00'; // Selalu kembalikan 2 desimal
    return new Intl.NumberFormat('us-EN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(number);
}