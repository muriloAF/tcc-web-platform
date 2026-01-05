//Informação
function mostrarModal(mensagem) {
  document.getElementById("mensagemErro").innerText = mensagem;
  document.getElementById("modalErro").style.display = "flex"; 
}


function fecharModal() {
  document.getElementById("modalErro").style.display = "none";
}

// Pegar o botão e o menu
const menuBtn = document.getElementById("menu-btn");
const menu = document.getElementById("menu");

// Quando clicar no botão, alternar classe
menuBtn.addEventListener("click", () => {
  menu.classList.toggle("show");
});




