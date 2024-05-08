
var timeoutID = 0;

function clearAll(windowObject) {
    var id = Math.max(
      windowObject.setInterval(noop, 1000),
      windowObject.setTimeout(noop, 1000)
    );
  
    while (id--) {
      windowObject.clearTimeout(id);
      windowObject.clearInterval(id);
    }
  
    function noop(){}
}
document.addEventListener('DOMContentLoaded', function() {
    var copyTargets = document.querySelectorAll('.copyTarget');
    function copyToClipboard(element) {
        var textToCopy = element.querySelector('span').innerText;
        var tempInput = document.createElement('textarea');
        tempInput.value = textToCopy.replace(' تومان', '').replace(' عدد', '');
        document.body.appendChild(tempInput);
        tempInput.select();
        tempInput.setSelectionRange(0, 99999); /* For mobile devices */
        document.execCommand('copy');
        document.body.removeChild(tempInput);
        showCustomAlert("در حافظه کپی شد", "#65CCA2");
    }
    copyTargets.forEach(function(element) {
        element.addEventListener('click', function() {
            copyToClipboard(element);
        });
    });
});

function showCustomAlert(text, color) {
    clearAll(window);
    var customAlert = document.createElement('div');
    customAlert.className = 'copy-Alert';
    customAlert.innerHTML = text;
    document.body.appendChild(customAlert);
    customAlert.style.display = 'block';
    customAlert.style.backgroundColor = color;
    timeoutID = setTimeout(function() {
        document.body.removeChild(customAlert);
    }, 2000);
}

function checkout(invoice) {
    result = true
    const url = `https://${window.location.hostname}/modules/gateways/tronpay.php`;
    const formData = new FormData();
    formData.append('check', 1);
    formData.append('invoiceId', invoice);
    const requestOptions = {
      method: 'POST',
      body: formData
    };
    
    fetch(url, requestOptions)
      .then(response => response.json())
      .then(data => {
            if (data['result'] == true) {
                showCustomAlert("پرداخت انجام شد.", "#65CCA2");
                window.location.href = `https://${window.location.hostname}/viewinvoice.php?id=${invoice}`;
            } else if (data['status'] == 404) {
                showCustomAlert("فاکتور یافت نشد.", "#B33420");
            } else if (data['status'] == 500) {
                showCustomAlert("خطا در تراکنش.", "#B33420");
            } else if (data['status'] == 406) {
                showCustomAlert("موجودی کیف پول : 0 ترون", "#B33420");
            }
        })
      .catch(error => console.error('Error:', error));
}
