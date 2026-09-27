document.getElementById('login').addEventListener('submit', async (event) => {
  event.preventDefault();
  const form = event.currentTarget;
  const erreur = document.getElementById('erreur');
  const bouton = form.querySelector('button');
  erreur.hidden = true;
  bouton.disabled = true;
  try {
    const res = await api('/api/auth/login', {
      method: 'POST',
      body: { username: form.username.value, password: form.password.value },
    });
    const suite = new URLSearchParams(window.location.search).get('suite');
    window.location.href = suite === res.accueil ? suite : res.accueil;
  } catch (err) {
    erreur.textContent = err.message;
    erreur.hidden = false;
    form.password.value = '';
    form.password.focus();
  } finally {
    bouton.disabled = false;
  }
});
