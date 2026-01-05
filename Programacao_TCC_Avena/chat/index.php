<?php include_once __DIR__ . '/../php/header_nav.php'; ?>
<!doctype html>
<html lang="pt-br">
	<head>
		<meta charset="utf-8">
		<title>Chat In RealTime</title>
		
		<link rel="stylesheet" href="style.css">
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.6.3/css/all.css" integrity="sha384-UHRtZLI+pbxtHCWp1t77Bi1L4ZtiqrqD80Kn4Z8NTSRyMA2Fd33n5dQ8lWUE00s/" crossorigin="anonymous">
	</head>
	
	<body>

	<!-- BOTÃO VOLTAR -->
	<style>
	.arrow-animated {
		position: relative;
		left: 30px;
		color: #917ba4;
		width: 30px;
		height: 30px;
		animation: floatLeft 1.6s ease-in-out infinite;
		margin-bottom: -38px;
		margin-left: -20px;
		z-index: 1000;
	}
	@keyframes floatLeft {
		0%   { transform: translateX(0); }
		50%  { transform: translateX(-2px); }
		100% { transform: translateX(0); }
	}
	</style>
	<a href="javascript:history.back()" class="arrow" style="text-decoration:none;">
	<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-arrow-left arrow-animated" viewBox="0 0 16 16">
		<path fill-rule="evenodd" d="M5.854 4.146a.5.5 0 0 1 0 .708L3.707 7H14.5a.5.5 0 0 1 0 1H3.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 0 1 .708 0z"/>
	</svg>
	</a>
	<!-- FIM BOTÃO VOLTAR -->

					<div class="result"></div>
        
					<section class="container">
			<article class="container_top_header">
				
				<div class="container_top">
				
					<div class="container_top_left">
						<img src="images/mestres-do-php.png" alt="Foto do Usuário Mestres do PHP" title="Foto do Usuário Mestres do PHP" class="border_gray">
					</div>
					
					<div class="container_top_center">
						<p class="container_top_center_firstLine">Mestres do PHP</p>
						<p class="container_top_center_secondLine"><span class="gray fa fa-circle"></span> OFFLINE</p>
					</div>
					<div class="clear"></div>
				</div>
				
				   <div class="container_top_right">
					   <p>
						   <a title="Procurar amigos" class="bg_green btn_search"><span class="fa fa-plus-circle"></span></a>
					   </p>
				   </div>
				<div class="clear"></div>
			</article>
			
			<div class="separator"></div>
			
			<article class="container_content">
				
				<div class="loaderHeader">

					<div class="container_content_margin">
						<div class="container_main_left">
							<img src="images/mestres-do-php.png" alt="Foto do Usuário Mestres do PHP" title="Foto do Usuário Mestres do PHP" class="border_gray">
						</div>
						
						<div class="mobile">
							<div class="container_main_center">
								<p class="container_main_center_firstLine">Mestres do PHP</p>
								<p class="container_main_center_secondLine"><span class="gray fa fa-circle"></span> OFFLINE </p>
							</div>
							
							<div class="container_main_right">
								<p>
									<a title="Chamar este usuário para o Chat" class="bg_gray btn_call" ><span class="fa fa-comments"></span> Chamar</a>
								</p>
							</div>
						</div>
						<div class="clear"></div>
					</div>
				</div>
				
				<div class="space_margin"></div>
			</article>
		</section>
		
		<section class="content">
			<article class="content_top">
				
				<div class="contentLoader">

					<div class="content_top_left">
						<img src="images/mestres-do-php.png" alt="Foto do Usuário Mestres do PHP" title="Foto do Usuário Mestres do PHP" class="border_gray">
					</div>
					
					<div class="content_top_center ">
						<p class="content_top_center_firstLine">Mestres do PHP</p>
						<p class="content_top_center_secondLine"><span class="green fa fa-circle"></span> ONLINE </p>
					</div>

				</div>
					
				<div class="content_top_right">
					<div class="topLoader">
					



						
					</div>
				</div>
				
				
				<div class="clear"></div>
			</article>
			
			<div class="separator"></div>
			
			<article class="content_header">
				
				<div class="loaderChat">
					
					<div class="content_header_margin">
						<div class="content_header_margin_img">
							<img src="images/mestres-do-php.png" alt="Foto do Usuário Mestres do PHP" title="Foto do Usuário Mestres do PHP" class="border_gray">
						</div>
						
						<div class="content_header_margin_text">
							<p class="content_main_center_chat">
							<span class="datetime">Mensagem ficará aqui</p>
						</div>
						
						<div class="clear"></div>
					</div>
					
				</div>
				<div class="text"></div>
				<div class="space_margin"></div>
			</article>
			
			<article class="content_form">
				<form method="post" id="form_chat">
					<div class="content_left">
						<input type="text" name="chat_text" disabled placeholder="Digite sua mensagem...">
					</div>
					
					<div class="content_right">
						<button class="bg_gray btn_disabled"><i class="fa fa-paper-plane"></i></button>
					</div>
					<div class="clear"></div>
				</form>
			</article>
		</section>
		
		<script src="jquery.js"></script>
		<script src="../js/chat.js"></script>
	</body>
</html>