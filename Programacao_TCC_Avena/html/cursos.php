<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    session_start();
    include_once(__DIR__ . '/../php/conexao.php');
    $img  = '../img/SemFoto.jpg';
    if((!isset($_SESSION['email']) == true) || (!isset($_SESSION['senha']) == true || $_SESSION['tipo'] == 'cliente')){
        unset($_SESSION['email']);
        unset($_SESSION['senha']);
        header('Location: ..\html\login.php');
        }else{
            $logado = $_SESSION['email'];
        }

    $sql = "SELECT * FROM prestadora WHERE email = '$logado'";
    
    $resultado = mysqli_query($conexao, $sql);
    $total = mysqli_num_rows($resultado);
    $prof = mysqli_fetch_assoc($resultado);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cursos - Avena</title>
    <link rel="stylesheet" href="..\css\cursos.css">
</head>
<body>



<!-- ===============================
       Menu Lateral
       =============================== -->
  <nav id="menu" class="hidden">
    <ul>
      <li><a href=".\quemSomos.php">Quem somos</a></li>
      <li><a href=".\cadastro.php">Cadastrar-se</a></li>
      <hr>
      <li><a href=".\sejaParceiro.php">Seja um Parceiro</a></li>
      <li><a href=".\Pagina_Inicial.html"><span class="Home">Home</span></a></li>
    </ul>
  </nav>

  <!-- ===============================
       Banner de Consentimento de Cookies
       =============================== -->
  <div id="cookie-banner" class="cookie-banner">
    <div class="cookie-content">
      <h4>Privacidade e Cookies</h4>
      <p>
        A Singularity Solutions utiliza cookies para oferecer uma experiência mais personalizada,
        melhorar o desempenho da plataforma e garantir o funcionamento seguro dos serviços.
        Ao aceitar, você concorda com o uso de cookies conforme nossa
        <a href="..\img\AVENA - Termos de Uso e Política de Privacidade.pdf" target="_blank">
          Política de Privacidade
        </a>.
      </p>
      <div class="cookie-buttons">
        <button id="accept-cookies" class="cookie-btn accept">Aceitar</button>
        <button id="decline-cookies" class="cookie-btn decline">Recusar</button>
      </div>
    </div>
  </div>



    <header>
    <nav>
      <div class="logo">
        <a href="Pagina_Inicial.html">
          <img src="../img/logoAvena.png" alt="Logo Avena">
        </a>
      </div>

      <div class="perfil-area">
        <span class="nome"><?= $prof['nome'];?></span>
        <img src="<?= $prof['imgperfil']?>" alt="Foto de perfil" class="perfil-foto">
        <button class="menu-icon" id="menu-btn">&#9776;</button>
      </div>
    </nav>
    </header>

          <!-- 
========================
// BOTÃO VOLTAR
========================
-->
<style>

 .arrow-animated {
    position: relative;
    top: 70px;
    left: 30px;
    color: #917ba4;
    width: 30px;  
    height:30px; 
    animation: floatLeft 1.6s ease-in-out infinite;
  }



  @keyframes floatLeft {
    0%   { transform: translateX(0); }
    50%  { transform: translateX(-2px); }
    100% { transform: translateX(0); }
  }

</style>
<a href= "..\html\bemVindoPrestadora.php">
<svg xmlns="http://www.w3.org/2000/svg" 
     width="20" height="20" fill="currentColor" 
     class="bi bi-arrow-left arrow-animated"
     viewBox="0 0 16 16">
  <path fill-rule="evenodd" 
        d="M5.854 4.146a.5.5 0 0 1 0 .708L3.707 7H14.5a.5.5 0 0 1 0 1H3.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 0 1 .708 0z"/>
</svg>
</a>
<!-- 
========================
// BOTÃO VOLTAR
========================
-->

    <main>
        <section class="cursos">
            <h2>Cursos</h2>
            <div class="cards">
                <div class="card">
                    <img src="../img/gestao-tempo.jpeg" alt="Gestão de Tempo">
                    <h3>Gestão de Tempo</h3>
                    <p>Aprenda a organizar seu tempo e tarefas de forma eficiente, aumentando sua produtividade e reduzindo o estresse. Desenvolva hábitos que ajudam a manter foco e disciplina no dia a dia pessoal e profissional.</p>
                    <a href='..\html\cursosDetalhes.php?id_curso=1' style="text-decoration:none; color:#fff"><button>Ver Detalhes</button></a>
                </div>

                <div class="card">
                    <img src="../img/marketing-digital.jpg" alt="Marketing Digital">
                    <h3>Marketing Digital</h3>
                    <p>Este curso apresenta conceitos e estratégias de marketing, ensinando como promover produtos e serviços, planejar campanhas e se comunicar de forma eficaz com diferentes públicos.</p>
                    <a href='..\html\cursosDetalhes.php?id_curso=12' style="text-decoration:none; color:#fff"><button>Ver Detalhes</button></a>
                </div>

                <div class="card">
                    <img src="../img/atendimento-cliente.jpg" alt="Atendimento ao Cliente">
                    <h3>Atendimento ao Cliente</h3>
                    <p>Este curso prepara você para se destacar no atendimento, ensinando técnicas para interagir de forma cordial, eficiente e profissional com clientes e público em geral. Aprenda a lidar com diferentes situações, manter a empatia e transmitir confiança em cada atendimento.</p>
                    <a href='..\html\cursosDetalhes.php?id_curso=2' style="text-decoration:none; color:#fff"><button>Ver Detalhes</button></a>
                </div>

                <div class="card">
                    <img src="../img/gestao-financeira.jpeg" alt="Educação Financeira">
                    <h3>Educação Financeira </h3>
                    <p>Desenvolva habilidades essenciais para controlar suas finanças pessoais. Aprenda a planejar gastos, poupar de forma inteligente e tomar decisões financeiras conscientes para alcançar seus objetivos.</p>
                    <a href='..\html\cursosDetalhes.php?id_curso=4' style="text-decoration:none; color:#fff"><button>Ver Detalhes</button></a>
                </div>

                <div class="card">
                    <img src="..\img\comunicacao-escrita.jpg" alt="Comunicação Escrita">
                    <h3>Comunicação Escrita </h3>
                    <p>Aprenda a escrever de forma clara, objetiva e adequada para o ambiente profissional. Este curso capacita você a transmitir ideias com eficiência, evitando mal-entendidos e melhorando sua imagem no trabalho ou nos estudos.</p>
                    <a href='..\html\cursosDetalhes.php?id_curso=3' style="text-decoration:none; color:#fff"><button>Ver Detalhes</button></a>
                </div>

                <div class="card">
                    <img src="..\img\manicure-e-pedicure.jpg" alt="Manicure e Pedicure ">
                    <h3>Manicure e Pedicure </h3>
                    <p>Aprenda técnicas profissionais de manicure e pedicure, com foco em estética, higiene e satisfação do cliente. </p>
                    <a href='..\html\cursosDetalhes.php?id_curso=5' style="text-decoration:none; color:#fff"><button>Ver Detalhes</button></a>
                </div>

                <div class="card">
                    <img src="..\img\maquiagem-profissional.jpg" alt="Maquiagem Profissional ">
                    <h3>Maquiagem Profissional </h3>
                    <p>Aprenda a criar maquiagens sofisticadas e adequadas para diferentes ocasiões. Desenvolva habilidades práticas em técnicas, cores e produtos, garantindo resultados profissionais e clientes satisfeitos.</p>
                    <a href='..\html\cursosDetalhes.php?id_curso=6' style="text-decoration:none; color:#fff"><button>Ver Detalhes</button></a>
                </div>

                <div class="card">
                    <img src="..\img\Trancista.jpg" alt="Trancista ">
                    <h3>Trancista</h3>
                    <p>Capacite-se para atuar como trancista profissional, dominando técnicas de tranças e penteados modernos, além de oferecer um atendimento de qualidade e cuidar da saúde capilar dos clientes.</p>
                    <a href='..\html\cursosDetalhes.php?id_curso=7' style="text-decoration:none; color:#fff"><button>Ver Detalhes</button></a>
                </div>

                <div class="card">
                    <img src="..\img\inclusividade.jpg" alt="Inclusividade">
                    <h3>Inclusividade</h3>
                    <p>Aprenda a promover diversidade e inclusão no atendimento ao público. Desenvolva atitudes que valorizam a pluralidade e combatem preconceitos, tornando-se um profissional mais consciente e preparado.</p>
                    <a href='..\html\cursosDetalhes.php?id_curso=8' style="text-decoration:none; color:#fff"><button>Ver Detalhes</button></a>
                </div>

                <div class="card">
                    <img src="..\img\empreendedorismo-inovacao.png" alt="Empreendedorismo e Inovação">
                    <h3>Empreendedorismo e Inovação</h3>
                    <p>Descubra como transformar ideias em negócios de sucesso. Este curso ensina conceitos de empreendedorismo, inovação e planejamento estratégico, preparando você para identificar oportunidades e criar soluções criativas.</p>
                    <a href='..\html\cursosDetalhes.php?id_curso=9' style="text-decoration:none; color:#fff"><button>Ver Detalhes</button></a>
                </div>

                <div class="card">
                    <img src="..\img\manipulacao-alimento.jpg" alt="Boas Práticas de Manipulação de Alimentos">
                    <h3>Boas Práticas de Manipulação de Alimentos</h3>
                    <p>Aprenda a manusear alimentos de forma segura, garantindo higiene e prevenção de contaminações. Ideal para quem atua na área de alimentação e deseja oferecer serviços com qualidade e segurança.</p>
                    <a href='..\html\cursosDetalhes.php?id_curso=10' style="text-decoration:none; color:#fff"><button>Ver Detalhes</button></a>
                </div>

                <div class="card">
                    <img src="..\img\congela-alimentos.jpg" alt="Congelamento de Alimentos">
                    <h3>Congelamento de Alimentos</h3>
                    <p>Aprenda técnicas corretas de congelamento e conservação de alimentos, mantendo qualidade, sabor e valor nutricional. Ideal para profissionais da área de alimentação que buscam segurança e eficiência no manuseio de alimentos.</p>
                    <a href='..\html\cursosDetalhes.php?id_curso=11' style="text-decoration:none; color:#fff"><button>Ver Detalhes</button></a>
                </div>

                <div class="card">
                    <img src="..\img\resiliencia.jpg" alt="Resiliência">
                    <h3>Resiliência</h3>
                    <p>Desenvolva a capacidade de superar desafios, mantendo equilíbrio emocional e foco nos objetivos. Este curso ensina técnicas para lidar com situações adversas e fortalecer sua postura pessoal e profissional.</p>
                    <a href='..\html\cursosDetalhes.php?id_curso=13' style="text-decoration:none; color:#fff"><button>Ver Detalhes</button></a>
                </div>

                <div class="card">
                    <img src="..\img\postura-imagem-profiss.jpg" alt="Postura e Imagem Profissional">
                    <h3>Postura e Imagem Profissional </h3>
                    <p>Aprenda a transmitir uma imagem profissional positiva, aprimorando postura, etiqueta, comunicação e aparência, essenciais para se destacar-se.</p>
                    <a href='..\html\cursosDetalhes.php?id_curso=14' style="text-decoration:none; color:#fff"><button>Ver Detalhes</button></a>
                </div>

                <div class="card">
                    <img src="..\img\balancos.jpg" alt="Análise de Balanços">
                    <h3>Análise de Balanços</h3>
                    <p>Aprenda a interpretar demonstrações financeiras e indicadores contábeis para tomar decisões estratégicas baseadas em dados concretos.</p>
                    <a href='..\html\cursosDetalhes.php?id_curso=15' style="text-decoration:none; color:#fff"><button>Ver Detalhes</button></a>
                </div>
            </div>
        </section>
    </main>
</body>
<script src="../js/login.js"></script>
  <script src="..\js\cookies.js"></script>
</html>