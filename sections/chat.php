<?php
/**
 * sections/chat.php — Área de chat activo.
 * Incluida desde index.php junto con sections/conversations.php.
 */
?>
<div class="content-area" id="content-area">

  <!-- Estado A: Sin conversación seleccionada -->
  <div class="welcome-screen" id="chat-welcome">
    <i class="fas fa-comments"></i>
    <h3>Selecciona una conversación</h3>
    <p>Elige un chat de la lista para comenzar a responder.</p>
  </div>

  <!-- Estado B: Conversación activa -->
  <div class="chat-wrap hidden" id="chat-wrap">

    <!-- Columna principal -->
    <div class="chat-main">

      <!-- Header del chat -->
      <div class="chat-header">
        <button class="btn-back-mobile" id="btn-back-mobile" onclick="App.backToList()" title="Volver">
          <i class="fas fa-arrow-left"></i>
        </button>
        <div class="conv-avatar attending" id="chat-avatar" style="width:42px;height:42px;font-size:1rem">?</div>
        <div class="chat-header-info">
          <div class="chat-contact-name" id="chat-contact-name">—</div>
          <div class="chat-contact-sub" id="chat-contact-sub">
            <span id="chat-status-badge" class="status-badge pending">Pendiente</span>
          </div>
        </div>
        <div class="chat-header-actions" id="chat-header-actions"></div>
      </div>

      <!-- Mensajes -->
      <div class="chat-messages" id="chat-messages"></div>

      <!-- Barra de composición -->
      <div class="chat-composer">

        <!-- Error inline -->
        <div class="chat-inline-error hidden" id="chat-inline-error"></div>

        <!-- Preview de archivo -->
        <div class="file-preview hidden" id="file-preview"></div>

        <!-- Campo de caption (oculto hasta que haya archivo) -->
        <div class="caption-field hidden" id="caption-wrap">
          <input type="text" id="caption-input" placeholder="Añadir descripción del archivo...">
        </div>

        <!-- Composición normal -->
        <div id="chat-composer-content">
          <div class="composer-row">
            <!-- Adjuntar -->
            <div class="composer-attach">
              <button class="btn-attach" id="btn-attach"
                      onclick="Chat.toggleAttachMenu()" title="Adjuntar archivo">
                <i class="fas fa-paperclip"></i>
              </button>
              <div class="attach-menu" id="attach-menu">
                <div class="attach-menu-item" onclick="Chat.triggerFileInput('image')">
                  <i class="fas fa-image"></i> Imagen
                  <small style="color:var(--texto-suave);margin-left:auto">JPG, PNG, WebP · 5MB</small>
                </div>
                <div class="attach-menu-item" onclick="Chat.triggerFileInput('document')">
                  <i class="fas fa-file-alt"></i> Documento
                  <small style="color:var(--texto-suave);margin-left:auto">PDF, DOCX, XLSX, ZIP · 10MB</small>
                </div>
              </div>
            </div>

            <!-- Textarea -->
            <textarea
              id="chat-textarea"
              class="composer-textarea"
              placeholder="Escribe un mensaje... (Enter para enviar)"
              rows="1"
            ></textarea>

            <!-- Botón enviar -->
            <button class="btn-send" id="btn-send" onclick="Chat.handleSend()" title="Enviar (Enter)">
              <i class="fas fa-paper-plane"></i>
            </button>
          </div>
        </div>

        <!-- Chat bloqueado -->
        <div id="chat-composer-locked" class="composer-locked hidden"></div>

      </div><!-- /chat-composer -->
    </div><!-- /chat-main -->

    <!-- Panel de información lateral -->
    <div class="chat-info-panel collapsed" id="chat-info-panel">
      <div class="info-section" style="position:sticky;top:0;background:var(--blanco);z-index:2;padding:10px 16px;border-bottom:1px solid var(--borde);">
        <strong style="font-size:.9rem">Información</strong>
      </div>
      <div id="chat-info-body"></div>
    </div>

  </div><!-- /chat-wrap -->
</div><!-- /content-area -->

<!-- Modal editar nombre del contacto -->
<div class="modal-overlay" id="modal-rename">
  <div class="modal-box" style="max-width:360px">
    <div class="modal-header">
      <span class="modal-title">
        <i class="fas fa-edit" style="color:var(--verde-mid);margin-right:6px"></i>
        Editar nombre del contacto
      </span>
      <button class="modal-close" onclick="Chat.closeRenameModal()">&times;</button>
    </div>
    <div style="padding:4px 0 16px">
      <input type="text" id="rename-input" maxlength="100"
             placeholder="Nombre del contacto"
             style="width:100%;padding:10px 12px;border:1px solid var(--borde);border-radius:var(--radius-sm);font-size:.95rem;box-sizing:border-box;background:var(--bg-input,#fff);color:var(--texto)"
             onkeydown="if(event.key==='Enter')Chat.doRename()">
    </div>
    <div class="modal-footer">
      <button class="btn-secondary" onclick="Chat.closeRenameModal()">Cancelar</button>
      <button class="btn-primary" onclick="Chat.doRename()">
        <i class="fas fa-save"></i> Guardar
      </button>
    </div>
  </div>
</div>

<!-- Modal de transferencia -->
<div class="modal-overlay" id="modal-transfer">
  <div class="modal-box" style="max-width:400px">
    <div class="modal-header">
      <span class="modal-title">
        <i class="fas fa-exchange-alt" style="color:var(--verde-mid);margin-right:6px"></i>
        Transferir conversación
      </span>
      <button class="modal-close" onclick="Chat.closeTransferModal()">&times;</button>
    </div>

    <p style="font-size:.85rem;color:var(--texto-suave);margin-bottom:14px">
      Selecciona el agente al que deseas transferir este chat. Solo aparecen agentes en línea.
    </p>

    <div id="transfer-agents-list" style="max-height:280px;overflow-y:auto;border:1px solid var(--borde);border-radius:var(--radius-sm)">
      <div style="padding:20px;text-align:center;color:var(--texto-suave);font-size:.85rem">
        <i class="fas fa-spinner fa-spin"></i> Cargando agentes...
      </div>
    </div>

    <div class="modal-footer">
      <button class="btn-secondary" onclick="Chat.closeTransferModal()">Cancelar</button>
      <button class="btn-primary" id="btn-transfer-confirm" onclick="Chat.doTransfer()" disabled>
        <i class="fas fa-exchange-alt"></i> Transferir
      </button>
    </div>
  </div>
</div>
