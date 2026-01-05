document.addEventListener("DOMContentLoaded", () => {
console.log("JS carregado!");


//Informação
function mostrarModal(mensagem) {
  document.getElementById("mensagemErro").innerText = mensagem;
  document.getElementById("modalErro").style.display = "flex"; 
}
window.mostrarModal = mostrarModal; // 🔥 Torna a função global


function fecharModal() {
  document.getElementById("modalErro").style.display = "none";
}
window.fecharModal = fecharModal;

// FOTO DE PERFIL
const inputFoto = document.getElementById('fotoPerfil');
const previewFoto = document.getElementById('previewFoto');

if (inputFoto && previewFoto) {
  inputFoto.addEventListener('change', function () {
    const file = this.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = function (e) {
        previewFoto.src = e.target.result;
      };
      reader.readAsDataURL(file);
    }
  });
}

// CONFIRMAR EXCLUSÃO
function confirmarExclusao() {
  document.getElementById("modalConfirmar").style.display = "flex";
}
window.confirmarExclusao = confirmarExclusao;

const btnCancelar = document.getElementById("btnCancelar");
const btnConfirmar = document.getElementById("btnConfirmar");

if (btnCancelar) {
  btnCancelar.addEventListener("click", () => {
      document.getElementById("modalConfirmar").style.display = "none";
  });
}

if (btnConfirmar) {
  btnConfirmar.addEventListener("click", () => {
      const form = document.createElement("form");
      form.method = "POST";
      form.action = "";

      const input = document.createElement("input");
      input.type = "hidden";
      input.name = "excluir";
      input.value = "1";

      form.appendChild(input);
      document.body.appendChild(form);
      form.submit();
  });
}

});
