// Pegar o botão e o menu
const menuBtn = document.getElementById("menu-btn");
const menu = document.getElementById("menu");

// Quando clicar no botão, alternar classe
menuBtn.addEventListener("click", () => {
  menu.classList.toggle("show");
});

