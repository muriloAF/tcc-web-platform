# Anexos no Chat

## Migração
Execute em `db_avena` (apenas uma vez):

```
ALTER TABLE mensagem ADD COLUMN tipo VARCHAR(16) NOT NULL DEFAULT 'text';
ALTER TABLE mensagem ADD COLUMN arquivo VARCHAR(255) NULL;
```

Se já existirem, ignore o erro. Para conferir:
```
SHOW COLUMNS FROM mensagem LIKE 'tipo';
SHOW COLUMNS FROM mensagem LIKE 'arquivo';
```

## Endpoint
`php/sendAttachment.php` aceita POST multipart:
- id_para: id do outro usuário (cliente ou prestadora)
- texto (opcional): legenda
- arquivo: file input

Retorno JSON:
```
{
  ok: true,
  id_mensagem: <int>,
  enviado_em: <timestamp>,
  tipo: 'image' | 'video' | 'audio' | 'file' | 'text',
  arquivo: 'uploads/messages/arquivo.ext',
  texto: 'legenda',
  mime: 'image/png',
  tamanho: 12345,
  colunas: { lido: true, tipo: true, arquivo: true }
}
```

## Tipos Permitidos
Imagens: jpeg, png, gif, webp
Vídeos: mp4, webm, ogv
Áudio: mp3, ogg, wav
Documentos: pdf, txt, doc, docx, xls, xlsx, ppt, pptx

Bloqueados: qualquer `video/x-*` e `audio/x-*`, executáveis e scripts (pela whitelist).

## Front-end (`chat.js`)
- Usa `sendAttachment()` para enviar.
- Mostra placeholder "Enviando ..." enquanto aguarda.
- Renderiza cada mensagem conforme `tipo`:
  - image -> `<img>`
  - video -> `<video controls>`
  - audio -> `<audio controls>`
  - file  -> `<a download>`
  - text  -> conteúdo puro

## Fallback Sem Migração
Se as colunas não existirem ainda:
- `openChat.php` retorna defaults.
- `sendAttachment.php` insere somente texto (arquivo ignora) sem quebrar.

## Segurança
- Nome sanitizado.
- Tamanho máximo: 15MB.
- MIME detectado via `finfo`, `mime_content_type` ou extensão.
- Diretório: `uploads/messages/` (criado se necessário).

## Próximos Melhoramentos
- Progresso de upload.
- Multi-upload simultâneo.
- Compressão de imagem no cliente.
- Miniatura de PDF.
