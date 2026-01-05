// Guard prevents redeclaration if script included multiple times
if (!window.__loginInit) {
  window.__loginInit = true;
  const menuBtn = document.getElementById("menu-btn");
  const menulogin = document.getElementById("menulogin");
  if (menuBtn && menulogin) {
    menuBtn.addEventListener("click", () => {
      menulogin.classList.toggle("show");
    });
  }
  window.mostrarModal = function(mensagem){
    const msgEl = document.getElementById("mensagemErro");
    const modal = document.getElementById("modalErro");
    if (msgEl) msgEl.innerText = mensagem;
    if (modal) modal.style.display = "flex";
  };
  window.fecharModal = function(){
    const modal = document.getElementById("modalErro");
    if (modal) modal.style.display = "none";
  };
}