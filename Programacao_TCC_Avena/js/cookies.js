document.addEventListener("DOMContentLoaded", () => {
  const cookieBanner = document.getElementById("cookie-banner");
  const acceptBtn = document.getElementById("accept-cookies");
  const declineBtn = document.getElementById("decline-cookies");

  const userConsent = localStorage.getItem("cookieConsent");

  // Se o usuário ainda não aceitou, mostra o banner
  if (userConsent !== "accepted") {
    cookieBanner.style.display = "block";
  }

  // Botão de aceitar → salva e nunca mais mostra
  acceptBtn.addEventListener("click", () => {
    localStorage.setItem("cookieConsent", "accepted");
    cookieBanner.style.display = "none";
  });

  // Botão de recusar → esconde, mas volta depois de um tempo
  declineBtn.addEventListener("click", () => {
    cookieBanner.style.display = "none";

    // Banner volta após 10 segundos (pode ajustar)
    setTimeout(() => {
      if (localStorage.getItem("cookieConsent") !== "accepted") {
        cookieBanner.style.display = "block";
      }
    }, 10000);
  });
});