import './bootstrap';

// Discount code form
const discountForm = document.getElementById('discount-form');

if (discountForm) {
    discountForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const email = document.getElementById('discount-email').value.trim();
        const errorEl = document.getElementById('discount-error');
        const submitBtn = document.getElementById('discount-submit');

        errorEl.classList.add('hidden');
        errorEl.textContent = '';
        submitBtn.disabled = true;
        submitBtn.textContent = 'Enviando...';

        try {
            await axios.post('/descuento', { email });

            document.getElementById('discount-form-wrap').classList.add('hidden');
            document.getElementById('discount-success-msg').textContent =
                `¡Código enviado a ${email}! Revisa tu bandeja de entrada.`;
            document.getElementById('discount-success').classList.remove('hidden');
        } catch (err) {
            let msg = 'Introduce un email válido.';

            if (err.response?.status === 429) {
                msg = 'Demasiados intentos. Inténtalo más tarde.';
            } else if (err.response?.data?.errors?.email?.[0]) {
                msg = err.response.data.errors.email[0];
            }

            errorEl.textContent = msg;
            errorEl.classList.remove('hidden');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Enviar →';
        }
    });
}
