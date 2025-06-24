document.addEventListener('DOMContentLoaded', function(){
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            let valid = true;
            form.querySelectorAll('input[required], select[required]').forEach(input => {
                if(!input.value.trim()){
                    valid = false;
                    input.style.border = "2px solid var(--danger)";
                } else {
                    input.style.border = "";
                }
            });
            if(!valid) {
                e.preventDefault();
                alert("Please fill in all required fields.");
            }
        });
    });
});
