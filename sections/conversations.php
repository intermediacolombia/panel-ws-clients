<?php
/**
 * sections/conversations.php — Columna de lista de conversaciones.
 * Incluida desde index.php, tiene acceso a $currentAgent.
 */
?>
<div class="conv-panel" id="conv-panel">

  <!-- Header -->
  <div class="conv-panel-header">
    <h2>
      Conversaciones
      <span class="unread-badge" id="pending-total-badge" style="display:none">0</span>
      <button class="btn-new-conv" onclick="App.openNewConversation()" title="Nueva conversación">
        <i class="fas fa-plus"></i>
      </button>
    </h2>
    <div class="conv-search">
      <i class="fas fa-search"></i>
      <input
        type="text"
        id="conv-search"
        placeholder="Buscar por nombre o número..."
        oninput="App.searchConversations(this.value)"
        autocomplete="off"
      >
    </div>
  </div>

  <!-- Tabs -->
  <div class="conv-tabs">
    <div class="conv-tab active" data-tab="all"
         onclick="App.setActiveTab('all')">
      Todos <span class="tab-count" id="tab-count-all">0</span>
    </div>
    <div class="conv-tab" data-tab="pending"
         onclick="App.setActiveTab('pending')">
      Pendientes <span class="tab-count" id="tab-count-pending">0</span>
    </div>
    <div class="conv-tab" data-tab="attending"
         onclick="App.setActiveTab('attending')">
      En atención <span class="tab-count" id="tab-count-attending">0</span>
    </div>
    <div class="conv-tab" data-tab="resolved"
         onclick="App.setActiveTab('resolved')">
      Resueltos <span class="tab-count" id="tab-count-resolved">0</span>
    </div>
  </div>

  <!-- Lista -->
  <div class="conv-list" id="conv-list">
    <div class="conv-empty">
      <i class="fas fa-comments"></i>
      <p>Cargando conversaciones...</p>
    </div>
  </div>

</div>

<!-- Modal: nueva conversación -->
<div class="modal-overlay" id="modal-new-conv">
  <div class="modal-box" style="max-width:420px">
    <div class="modal-header">
      <span class="modal-title">
        <i class="fas fa-comment-medical" style="color:var(--verde-wa);margin-right:6px"></i>
        Nueva conversación
      </span>
      <button class="modal-close" onclick="App.closeModal('modal-new-conv')">&times;</button>
    </div>

    <div class="form-row">
      <label>Número de teléfono *</label>
      <input type="tel" id="new-conv-phone" placeholder="573001234567 (con código de país)" autocomplete="off">
      <small style="color:var(--texto-suave);margin-top:4px;display:block">Sin +, sin espacios ni guiones. Ej: 573001234567</small>
    </div>

    <div class="form-row">
      <label>Nombre del contacto <span style="font-weight:400;text-transform:none">(opcional)</span></label>
      <input type="text" id="new-conv-name" placeholder="Juan Pérez">
    </div>

    <div class="form-row">
      <label>Mensaje inicial *</label>
      <textarea id="new-conv-message" rows="4" placeholder="Escribe el primer mensaje que recibirá el contacto..." style="resize:vertical"></textarea>
    </div>

    <div class="form-error hidden" id="new-conv-error">
      <i class="fas fa-exclamation-circle"></i> <span></span>
    </div>

    <div class="modal-footer">
      <button class="btn-secondary" onclick="App.closeModal('modal-new-conv')">Cancelar</button>
      <button class="btn-primary" id="btn-new-conv-send" onclick="App.sendNewConversation()">
        <i class="fas fa-paper-plane"></i> Enviar y abrir
      </button>
    </div>
  </div>
</div>
