(() => {
  'use strict';

  const body = document.body;
  const basePath = body.dataset.base || '/etki/frontend';
  const apiBase = `${basePath}/../backend/api`;
  const state = {
    user: null,
    csrf: '',
    categories: [],
    missions: [],
    groups: [],
    filter: 'all',
    search: '',
    map: null,
    mapHasFit: false,
    markers: [],
    activeMission: null,
    activeGroup: null,
    groupPoller: null,
    groupSending: false,
    activeDirect: null,
    directPoller: null,
    directSending: false,
    directConversations: [],
    missionPoller: null,
    addressSearching: false,
    justCreatedMissionId: null,
  };

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
  const escapeHtml = (value = '') => String(value).replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  const statusLabel = status => ({
    open: 'Açık', accepted: 'Kabul edildi', in_progress: 'Devam ediyor', verification_pending: 'Doğrulama bekliyor',
    solution_check: 'Çözüm kontrolü', completed: 'Tamamlandı', partially_completed: 'Kısmen tamamlandı', rejected: 'Reddedildi',
    authority_routed: 'Kuruma yönlendirildi', authority_waiting: 'Kurum cevabı bekleniyor', safety_closed: 'Güvenlik nedeniyle kapalı',
    cancelled: 'İptal edildi'
  }[status] || status);
  const statusClass = status => ['completed'].includes(status) ? 'green' : ['verification_pending','solution_check','partially_completed'].includes(status) ? 'warning' : ['rejected','safety_closed','cancelled'].includes(status) ? 'danger' : '';
  const missionStatusLabel = mission => Number(mission?.is_expired) === 1 && !['completed','cancelled'].includes(mission.status) ? 'Süresi doldu' : statusLabel(mission.status);
  const missionStatusClass = mission => Number(mission?.is_expired) === 1 ? 'danger' : statusClass(mission.status);
  const formatDate = value => value ? new Intl.DateTimeFormat('tr-TR', {dateStyle:'medium', timeStyle:'short'}).format(new Date(value.replace(' ', 'T'))) : '—';
  const roleLabel = role => ({owner:'Kurucu', administrator:'Yönetici', assistant:'Yönetici yardımcısı', leader:'Lider', moderator:'Moderatör', event_manager:'Etkinlik sorumlusu', safety_officer:'Güvenlik sorumlusu', verifier:'Doğrulayıcı', member:'Üye'}[role] || 'Üye');
  const groupIcon = type => type === 'cleaning' ? '♻️' : type === 'accessibility' ? '♿' : type === 'animal' ? '🐾' : '♧';
  const uploadUrl = path => path ? encodeURI(`${basePath}/../${String(path).replace(/^\/+/, '')}`) : '';
  const isGroupManagerRole = role => ['owner','administrator','assistant'].includes(role);

  async function api(path, options = {}) {
    const method = String(options.method || 'GET').toUpperCase();
    const headers = new Headers(options.headers || {});
    if (!(options.body instanceof FormData)) headers.set('Content-Type', 'application/json');
    if (state.csrf && method !== 'GET') headers.set('X-CSRF-Token', state.csrf);
    const separator = path.includes('?') ? '&' : '?';
    const url = method === 'GET' ? `${apiBase}/${path}${separator}_=${Date.now()}` : `${apiBase}/${path}`;
    const response = await fetch(url, {...options, method, headers, credentials:'same-origin', cache:'no-store'});
    const data = await response.json().catch(() => ({ok:false, message:'Sunucudan geçersiz yanıt alındı.'}));
    if (!response.ok || data.ok === false) throw new Error(data.message || 'İşlem başarısız.');
    return data;
  }

  function toast(message, type = 'success') {
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.textContent = message;
    $('#toastStack').appendChild(el);
    setTimeout(() => el.remove(), 3500);
  }

  function requireLogin() {
    if (state.user) return true;
    $('#authModal').showModal();
    toast('Bu işlem için giriş yapmalısınız.', 'error');
    return false;
  }

  function openSection(name) {
    $$('.page-section').forEach(section => section.classList.toggle('active', section.id === `section-${name}`));
    $$('.nav-item').forEach(btn => btn.classList.toggle('active', btn.dataset.section === name));
    $('#sidebar').classList.remove('open');
    if (name === 'map') setTimeout(initMap, 80);
    if (name === 'profile' && state.user) loadProfile();
    if (name === 'messages') {
      if (state.user) loadDirectConversations();
      else renderDirectConversations();
    }
    if (name === 'admin' && state.user?.role === 'admin') loadAdmin();
  }

  function renderAuth() {
    const logged = !!state.user;
    $('#topUserText').textContent = logged ? state.user.username : 'Giriş yap';
    $('#topAvatar').textContent = logged ? state.user.username.slice(0, 1).toLocaleUpperCase('tr-TR') : 'E';
    $$('.admin-only').forEach(el => el.classList.toggle('hidden', state.user?.role !== 'admin'));
    $('#profileGuest').classList.toggle('hidden', logged);
    $('#profileContent').classList.toggle('hidden', !logged);
    $('#messagesGuest')?.classList.toggle('hidden', logged);
    $('#messagesContent')?.classList.toggle('hidden', !logged);
    if (!logged) {
      state.directConversations = [];
      renderDirectConversations();
    }
  }

  function categoryById(id) { return state.categories.find(c => Number(c.id) === Number(id)); }

  function filteredMissions() {
    return state.missions.filter(m => {
      const categoryMatch = state.filter === 'all' || m.category_slug === state.filter;
      const q = state.search.trim().toLocaleLowerCase('tr-TR');
      const searchMatch = !q || `${m.title || ''} ${m.description || ''} ${m.location_name || ''} ${m.category_name || ''}`.toLocaleLowerCase('tr-TR').includes(q);
      return categoryMatch && searchMatch;
    });
  }

  function missionCard(m, compact = false) {
    const location = String(m.location_name || '').trim();
    const description = String(m.description || '');
    const compactText = location && location !== 'Genel bölge paylaşılmadı' ? location : m.category_name;
    const createdClass = Number(state.justCreatedMissionId) === Number(m.id) ? 'just-created' : '';
    const isMine = state.user && Number(state.user.id) === Number(m.creator_id);
    return `
      <article class="mission-card ${compact ? 'compact' : ''} ${createdClass}" data-mission-id="${m.id}">
        <div class="mission-icon">${escapeHtml(m.category_icon)}</div>
        <div>
          ${compact ? '' : `<span class="eyebrow">${escapeHtml(m.category_name)}</span>`}
          <h3>${escapeHtml(m.title)}</h3>
          <p>${escapeHtml(compact ? compactText : description.slice(0, 130) + (description.length > 130 ? '…' : ''))}</p>
          <div class="mission-meta">
            ${compact || !location || location === 'Genel bölge paylaşılmadı' ? '' : `<span class="chip">⌖ ${escapeHtml(location)}</span>`}
            <span class="chip green">+${m.xp_reward} XP</span>
            ${isMine ? '<span class="chip">Senin görevin</span>' : ''}
            ${compact ? '' : `<span class="chip">${m.risk_level === 'low' ? 'Düşük risk' : 'Orta risk'}</span>`}
          </div>
        </div>
        ${compact ? `<span class="chip status-pill ${missionStatusClass(m)}">${missionStatusLabel(m)}</span>` : `<span class="chip status-pill ${missionStatusClass(m)}" style="position:absolute;right:14px;top:14px">${missionStatusLabel(m)}</span>`}
      </article>`;
  }

  function renderStats() {
    const total = state.missions.length;
    const open = state.missions.filter(m => ['open','accepted','in_progress'].includes(m.status)).length;
    const completed = state.missions.filter(m => m.status === 'completed').length;
    const verify = state.missions.filter(m => ['verification_pending','solution_check'].includes(m.status)).length;
    const cards = [
      ['Açık görev', open, 'Katkı bekliyor'],
      ['Doğrulanan etki', completed, 'Gerçek sonuç'],
      ['Kontrol bekleyen', verify, 'Topluluk doğrulaması'],
      ['Aktif ekip', state.groups.length, 'Birlikte hareket']
    ];
    const html = cards.map(([label,value,small]) => `<div class="stat-card"><span>${label}</span><strong>${value}</strong><small>${small}</small></div>`).join('');
    $('#homeStats').innerHTML = html;
    $('#impactStats').innerHTML = html;
    $('#communityScore').textContent = completed;

    const percent = Math.min(100, Math.round((completed / Math.max(1, total)) * 100));
    $('#impactPercent').textContent = `${percent}%`;
    $('#impactRing').style.background = `conic-gradient(var(--green) ${percent * 3.6}deg, #e7edf2 0deg)`;
    $('#impactText').textContent = `${completed} görev doğrulanmış sonuç üretti.`;
  }

  function renderImpact() {
    const counts = state.categories.map(c => ({
      ...c,
      count: state.missions.filter(m => m.category_slug === c.slug).length,
      completed: state.missions.filter(m => m.category_slug === c.slug && m.status === 'completed').length
    }));
    const max = Math.max(1, ...counts.map(c => c.count));
    $('#impactBars').innerHTML = counts.slice(0,4).map(c => `<div class="mini-bar-line"><span>${escapeHtml(c.name)}</span><div class="bar-track"><div class="bar-fill" style="width:${Math.round(c.count/max*100)}%"></div></div><b>${c.count}</b></div>`).join('');
    $('#categoryImpact').innerHTML = counts.map(c => `<div class="category-impact-row"><strong>${escapeHtml(c.icon)} ${escapeHtml(c.name)}</strong><div class="bar-track"><div class="bar-fill" style="width:${Math.round(c.count/max*100)}%"></div></div><span>${c.completed}/${c.count}</span></div>`).join('');
  }

  function renderMissions() {
    const list = filteredMissions();
    $('#missionGrid').innerHTML = list.map(m => missionCard(m)).join('');
    $('#missionEmpty').classList.toggle('hidden', list.length > 0);
    $('#featuredMissions').innerHTML = state.missions.slice(0, 5).map(m => missionCard(m, true)).join('');
    const mapMissions = state.missions.filter(m => Number(m.is_map_active) === 1);
    $('#mapMissionList').innerHTML = mapMissions.length ? mapMissions.slice(0, 12).map(m => missionCard(m, true)).join('') : '<div class="empty-state">Haritada gösterilecek aktif görev yok.</div>';
    bindMissionCards();
    updateMapMarkers();
  }

  function renderCategories() {
    $('#categoryFilters').innerHTML = `<button class="filter-pill active" data-filter="all">Tümü</button>` + state.categories.map(c => `<button class="filter-pill" data-filter="${escapeHtml(c.slug)}">${escapeHtml(c.icon)} ${escapeHtml(c.name)}</button>`).join('');
    $('#missionCategory').innerHTML = state.categories.map(c => `<option value="${c.id}" data-slug="${escapeHtml(c.slug)}">${escapeHtml(c.icon)} ${escapeHtml(c.name)}</option>`).join('');
    updateMissionForm();
    $$('.filter-pill').forEach(btn => btn.addEventListener('click', () => {
      state.filter = btn.dataset.filter;
      $$('.filter-pill').forEach(b => b.classList.toggle('active', b === btn));
      renderMissions();
    }));
  }

  function renderGroups() {
    $('#teamGrid').innerHTML = state.groups.map(g => {
      const isMember = g.my_status === 'active';
      const action = isMember
        ? `<button class="btn btn-primary open-team" data-group-id="${g.id}">Grubu aç</button>`
        : `<button class="btn btn-outline join-team" data-group-id="${g.id}">Katıl</button>`;
      const chatState = Number(g.chat_enabled) === 1 ? 'Sohbet açık' : 'Yalnızca yöneticiler';
      return `
      <article class="team-card ${isMember ? 'is-member' : ''}">
        <div class="team-cover"><span>${groupIcon(g.type)}</span><small>${escapeHtml(chatState)}</small></div>
        <span class="eyebrow">${escapeHtml(g.area)}</span>
        <h3>${escapeHtml(g.name)}</h3>
        <p>${escapeHtml(g.description || 'Ortak amaç etrafında güvenli ve doğrulanabilir görevler.')}</p>
        <div class="mission-meta"><span class="chip">${g.member_count} üye</span><span class="chip green">Seviye ${g.level}</span>${isMember ? `<span class="chip">${escapeHtml(roleLabel(g.my_role))}</span>` : ''}</div>
        <div class="team-footer"><small>Kurucu: ${escapeHtml(g.owner_name)}</small>${action}</div>
      </article>`;
    }).join('');
    $$('.join-team').forEach(btn => btn.addEventListener('click', () => joinTeam(btn.dataset.groupId)));
    $$('.open-team').forEach(btn => btn.addEventListener('click', () => openGroup(btn.dataset.groupId)));
  }

  function bindMissionCards() {
    $$('[data-mission-id]').forEach(card => card.addEventListener('click', () => openMission(card.dataset.missionId)));
  }

  async function openMission(id) {
    const mission = state.missions.find(m => Number(m.id) === Number(id));
    if (!mission) return;
    state.activeMission = mission;
    let answers = [];
    if (mission.category_slug === 'question') {
      try { answers = (await api(`answers.php?mission_id=${mission.id}`)).answers; } catch (_) {}
    }
    const isCreator = state.user && Number(state.user.id) === Number(mission.creator_id);
    const isExpired = Number(mission.is_expired) === 1;
    const actionButtons = [];
    if (!state.user) actionButtons.push(`<button class="btn btn-primary" data-open-auth>Giriş yap</button>`);
    if (state.user && !isExpired && mission.status === 'open' && !isCreator && mission.category_slug !== 'question') actionButtons.push(`<button class="btn btn-primary" data-action="accept">Görevi kabul et</button>`);
    if (state.user && !isExpired && mission.status === 'accepted' && !isCreator) actionButtons.push(`<button class="btn btn-primary" data-action="start">Başlat</button>`);
    if (state.user && ['accepted','in_progress'].includes(mission.status) && !isCreator) actionButtons.push(`<button class="btn btn-primary" data-open-complete>Kanıt gönder</button>`);
    if (state.user && ['verification_pending','solution_check'].includes(mission.status) && !isCreator) actionButtons.push(`<button class="btn btn-primary" data-open-verify>Doğrula</button>`);
    if (state.user && (isCreator || ['accepted','in_progress','verification_pending'].includes(mission.status))) actionButtons.push(`<button class="btn btn-outline" data-toggle-chat>Görev sohbeti</button>`);

    $('#missionDetail').innerHTML = `
      <div class="mission-detail-head">
        <div class="mission-icon">${escapeHtml(mission.category_icon)}</div>
        <div><span class="eyebrow">${escapeHtml(mission.category_name)}</span><h2>${escapeHtml(mission.title)}</h2><div class="mission-meta"><span class="chip ${missionStatusClass(mission)}">${missionStatusLabel(mission)}</span><span class="chip green">+${mission.xp_reward} XP</span><span class="chip">${escapeHtml(mission.risk_level)} risk</span></div></div>
      </div>
      <div class="detail-box"><strong>Açıklama</strong><p>${escapeHtml(mission.description)}</p></div>
      <div class="detail-box"><strong>Yaklaşık konum</strong><p>⌖ ${escapeHtml(mission.location_name || 'Genel bölge paylaşılmadı')}</p></div>
      <div class="detail-box"><strong>Gereken kanıt</strong><p>${escapeHtml(mission.required_evidence)} · Son tarih: ${formatDate(mission.expires_at)}</p>${isExpired ? '<small class="danger-text">Bu görevin süresi doldu ve haritadan kaldırıldı.</small>' : ''}</div>
      ${mission.category_slug === 'question' ? `
        <div class="detail-box"><strong>Güncel cevaplar</strong><div class="answer-list">${answers.length ? answers.map(a => `<div class="answer-item"><strong>${escapeHtml(a.username)} · güven ${a.confidence}/5</strong><p>${escapeHtml(a.answer)}</p></div>`).join('') : '<p>Henüz cevap yok.</p>'}</div>
        ${state.user && !isCreator && !isExpired ? `<form id="answerForm" class="inline-form"><input name="answer" placeholder="Güncel cevabını yaz…" required><button class="btn btn-primary">Cevapla</button></form>` : ''}</div>` : ''}
      <div class="detail-actions">${actionButtons.join('')}</div>
      <div id="chatArea" class="detail-box hidden"><strong>Göreve bağlı sohbet</strong><div class="message-list" id="messageList"></div><form id="messageForm" class="inline-form"><input name="body" maxlength="500" placeholder="Yalnızca görevle ilgili mesaj yaz…"><button class="btn btn-primary">Gönder</button></form></div>`;

    $('#missionModal').showModal();
    $$('[data-action]', $('#missionDetail')).forEach(btn => btn.addEventListener('click', () => missionAction(btn.dataset.action, mission.id)));
    $('[data-open-complete]', $('#missionDetail'))?.addEventListener('click', () => { $('#completeMissionId').value = mission.id; $('#completeModal').showModal(); });
    $('[data-open-verify]', $('#missionDetail'))?.addEventListener('click', () => { $('#verifyMissionId').value = mission.id; $('#verifyModal').showModal(); });
    $('[data-open-auth]', $('#missionDetail'))?.addEventListener('click', () => $('#authModal').showModal());
    $('[data-toggle-chat]', $('#missionDetail'))?.addEventListener('click', async () => { $('#chatArea').classList.toggle('hidden'); await loadMessages(mission.id); });
    $('#answerForm')?.addEventListener('submit', submitAnswer);
    $('#messageForm')?.addEventListener('submit', submitMessage);
  }

  async function missionAction(action, missionId) {
    if (!requireLogin()) return;
    try {
      const data = await api('mission_action.php', {method:'POST', body:JSON.stringify({action, mission_id:missionId})});
      toast(data.message);
      $('#missionModal').close();
      await refreshData();
    } catch (e) { toast(e.message, 'error'); }
  }

  async function submitAnswer(event) {
    event.preventDefault();
    const input = event.currentTarget.elements.answer;
    try {
      const data = await api('answers.php', {method:'POST', body:JSON.stringify({mission_id:state.activeMission.id, answer:input.value, confidence:4})});
      toast(data.message);
      await refreshData();
      await openMission(state.activeMission.id);
    } catch (e) { toast(e.message, 'error'); }
  }

  async function loadMessages(missionId) {
    try {
      const data = await api(`messages.php?mission_id=${missionId}`);
      $('#messageList').innerHTML = data.messages.length ? data.messages.map(m => `<div class="message-item"><strong>${escapeHtml(m.username)}</strong><p>${escapeHtml(m.body)}</p></div>`).join('') : '<p>Henüz mesaj yok.</p>';
    } catch (e) { toast(e.message, 'error'); }
  }

  async function submitMessage(event) {
    event.preventDefault();
    const input = event.currentTarget.elements.body;
    try {
      const data = await api('messages.php', {method:'POST', body:JSON.stringify({mission_id:state.activeMission.id, body:input.value})});
      input.value = '';
      toast(data.message);
      await loadMessages(state.activeMission.id);
    } catch (e) { toast(e.message, 'error'); }
  }

  async function joinTeam(groupId) {
    if (!requireLogin()) return;
    try {
      const data = await api('groups.php', {method:'POST', body:JSON.stringify({action:'join', group_id:Number(groupId)})});
      toast(data.message);
      await loadGroups();
      await openGroup(groupId);
    } catch (e) { toast(e.message, 'error'); }
  }

  function stopGroupPolling() {
    if (state.groupPoller) window.clearInterval(state.groupPoller);
    state.groupPoller = null;
  }

  function groupRoleOptions(member, data) {
    if (!data.permissions.can_assign_roles || !canActorManageMember(data, member) || member.role === 'owner' || Number(member.user_id) === Number(state.user?.id)) {
      return `<span class="member-role">${escapeHtml(roleLabel(member.role))}</span>`;
    }
    const actorRole = data.membership.role;
    const allowed = actorRole === 'owner' ? ['administrator','assistant','member'] : ['assistant','member'];
    const options = allowed.includes(member.role) ? allowed : [member.role, ...allowed];
    return `<select class="member-role-select" data-member-role data-user-id="${member.user_id}" aria-label="${escapeHtml(member.username)} rolü">
      ${options.map(role => `<option value="${role}" ${member.role === role ? 'selected' : ''}>${escapeHtml(roleLabel(role))}</option>`).join('')}
    </select>`;
  }

  function canActorManageMember(data, member) {
    const rank = role => role === 'owner' ? 100 : role === 'administrator' ? 80 : role === 'assistant' ? 60 : 10;
    return data.permissions.can_manage_members && Number(member.user_id) !== Number(state.user?.id) && rank(data.membership.role) > rank(member.role);
  }

  function renderGroupMembers(data) {
    const container = $('#groupMemberList');
    if (!container) return;
    container.innerHTML = data.members.map(member => {
      const manageable = canActorManageMember(data, member);
      const isSelf = Number(member.user_id) === Number(state.user?.id);
      const mutedText = Number(member.is_muted) === 1 ? `Susturuldu · ${formatDate(member.muted_until)}` : '';
      return `<div class="group-member-row">
        <div class="member-avatar">${escapeHtml(member.username.slice(0,1).toLocaleUpperCase('tr-TR'))}</div>
        <div class="member-info"><strong>${escapeHtml(member.username)}</strong>${groupRoleOptions(member, data)}${mutedText ? `<small class="muted-label">${escapeHtml(mutedText)}</small>` : ''}</div>
        ${!isSelf ? `<div class="member-actions">
          <button class="mini-action direct-action" data-member-action="direct_message" data-user-id="${member.user_id}">Özel mesaj</button>
          ${manageable ? `${Number(member.is_muted) === 1
            ? `<button class="mini-action" data-member-action="unmute_member" data-user-id="${member.user_id}">Susturmayı kaldır</button>`
            : `<select class="mute-duration" data-mute-duration="${member.user_id}" aria-label="Susturma süresi"><option value="10">10 dk</option><option value="60" selected>1 saat</option><option value="480">8 saat</option><option value="1440">1 gün</option><option value="10080">1 hafta</option></select><button class="mini-action" data-member-action="mute_member" data-user-id="${member.user_id}">Sustur</button>`}
          <button class="mini-action danger-text" data-member-action="remove_member" data-user-id="${member.user_id}">Çıkar</button>` : ''}
        </div>` : ''}
      </div>`;
    }).join('');
  }

  function renderGroupMessages(data, forceBottom = false) {
    const list = $('#groupMessageList');
    if (!list) return;
    const nearBottom = list.scrollHeight - list.scrollTop - list.clientHeight < 100;
    list.innerHTML = data.messages.length ? data.messages.map(message => {
      const mine = Number(message.sender_id) === Number(state.user?.id);
      const canDelete = mine || data.permissions.can_delete_any_message;
      return `<div class="group-message ${mine ? 'mine' : ''}" data-message-id="${message.id}">
        <div class="message-bubble">
          <div class="message-author"><strong>${escapeHtml(message.username)}</strong><span>${escapeHtml(roleLabel(message.sender_role))}</span></div>
          ${message.image_path ? `<a class="group-photo-link" href="${uploadUrl(message.image_path)}" target="_blank" rel="noopener"><img src="${uploadUrl(message.image_path)}" alt="${escapeHtml(message.username)} tarafından gönderilen grup fotoğrafı"></a>` : ''}
          ${message.body ? `<p>${escapeHtml(message.body)}</p>` : ''}
          <div class="message-time"><span>${formatDate(message.created_at)}</span>${canDelete ? `<button data-delete-group-message="${message.id}" title="Mesajı sil">Sil</button>` : ''}</div>
        </div>
      </div>`;
    }).join('') : '<div class="group-chat-empty">Henüz mesaj yok. İlk mesajı gönder.</div>';
    if (forceBottom || nearBottom) list.scrollTop = list.scrollHeight;
  }

  function setGroupComposerState(data = state.activeGroup) {
    const composer = $('#groupMessageForm');
    if (!composer || !data) return;
    const allowed = !!data.permissions?.can_send;
    const textarea = $('#groupMessageText', composer);
    const fileInput = $('#groupPhotoInput', composer);
    const submit = $('button[type="submit"]', composer);
    if (textarea) {
      textarea.disabled = !allowed;
      textarea.readOnly = allowed && state.groupSending;
      textarea.placeholder = allowed ? (state.groupSending ? 'Mesaj gönderiliyor…' : 'Gruba mesaj yaz…') : 'Mesaj gönderme yetkin yok';
    }
    if (fileInput) fileInput.disabled = !allowed || state.groupSending;
    if (submit) {
      submit.disabled = !allowed || state.groupSending;
      submit.textContent = state.groupSending ? 'Gönderiliyor…' : 'Gönder';
    }
  }

  function bindGroupPhotoInput() {
    const input = $('#groupPhotoInput');
    if (!input || input.dataset.bound === '1') return;
    input.dataset.bound = '1';
    input.addEventListener('change', event => {
      const file = event.target.files?.[0] || null;
      $('#groupPhotoName').textContent = file ? file.name : 'Fotoğraf seçilmedi';
    });
  }

  function clearGroupPhotoInput() {
    const input = $('#groupPhotoInput');
    if (input) {
      const replacement = input.cloneNode(true);
      replacement.value = '';
      replacement.removeAttribute('data-bound');
      input.replaceWith(replacement);
    }
    const label = $('#groupPhotoName');
    if (label) label.textContent = 'Fotoğraf seçilmedi';
    bindGroupPhotoInput();
  }

  function updateGroupModal(data, forceBottom = false) {
    state.activeGroup = data;
    const group = data.group;
    $('#groupChatStatus').textContent = group.chat_enabled ? 'Tüm üyeler mesaj gönderebilir' : 'Yalnızca yöneticiler mesaj gönderebilir';
    $('#groupChatStatus').className = `chat-status ${group.chat_enabled ? 'open' : 'closed'}`;
    $('#groupMemberCount').textContent = `${data.members.length} üye`;
    const toggle = $('#groupChatToggle');
    if (toggle) {
      toggle.checked = !!group.chat_enabled;
      toggle.disabled = !data.permissions.can_manage_chat;
    }
    const notice = $('#groupChatNotice');
    if (data.permissions.is_muted) {
      notice.textContent = `Bu grupta ${formatDate(data.membership.muted_until)} tarihine kadar susturuldun.`;
      notice.className = 'group-chat-notice danger';
    } else if (!group.chat_enabled && !isGroupManagerRole(data.membership.role)) {
      notice.textContent = 'Yönetici mesajlaşmayı üyeler için kapattı. Mesajları okuyabilirsin ancak gönderemezsin.';
      notice.className = 'group-chat-notice warning';
    } else {
      notice.textContent = 'Telefon numarası, kesin konum veya özel kişisel bilgi paylaşma.';
      notice.className = 'group-chat-notice';
    }
    setGroupComposerState(data);
    $('#groupManagementBox')?.classList.toggle('hidden', !data.permissions.can_manage_chat);
    $('#leaveGroupButton')?.classList.toggle('hidden', data.membership.role === 'owner');
    renderGroupMessages(data, forceBottom);
    renderGroupMembers(data);
  }

  async function refreshGroupChat(forceBottom = false, silent = false) {
    if (!state.activeGroup?.group?.id || (silent && state.groupSending)) return;
    try {
      const data = await api(`group_chat.php?group_id=${state.activeGroup.group.id}`);
      updateGroupModal(data, forceBottom);
    } catch (e) {
      if (!silent) toast(e.message, 'error');
    }
  }

  async function openGroup(groupId) {
    if (!requireLogin()) return;
    stopGroupPolling();
    try {
      const data = await api(`group_chat.php?group_id=${Number(groupId)}`);
      state.activeGroup = data;
      $('#groupDetail').innerHTML = `
        <div class="group-modal-heading">
          <div class="group-heading-icon">${groupIcon(data.group.type)}</div>
          <div><span class="eyebrow">${escapeHtml(data.group.area)}</span><h2>${escapeHtml(data.group.name)}</h2><div class="group-heading-meta"><span id="groupMemberCount">${data.members.length} üye</span><span id="groupChatStatus" class="chat-status"></span><span>${escapeHtml(roleLabel(data.membership.role))}</span></div></div>
        </div>
        <div class="group-chat-layout">
          <section class="group-chat-panel">
            <div id="groupChatNotice" class="group-chat-notice"></div>
            <div id="groupMessageList" class="group-message-list" aria-live="polite"></div>
            <form id="groupMessageForm" class="group-composer" enctype="multipart/form-data">
              <textarea id="groupMessageText" name="body" maxlength="1000" rows="2" placeholder="Gruba mesaj yaz…"></textarea>
              <div class="composer-actions">
                <label class="photo-picker">📷 Fotoğraf<input id="groupPhotoInput" type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
                <span id="groupPhotoName" class="selected-file">Fotoğraf seçilmedi</span>
                <button class="btn btn-primary" type="submit">Gönder</button>
              </div>
            </form>
          </section>
          <aside class="group-side-panel">
            <div class="group-side-section" id="groupManagementBox">
              <strong>Grup ayarları</strong>
              <label class="switch-row"><span><b>Üyeler mesaj yazabilsin</b><small>Kapalıyken sadece yöneticiler yazar.</small></span><input id="groupChatToggle" type="checkbox"></label>
            </div>
            <div class="group-side-section members-section">
              <div class="side-section-title"><strong>Üyeler</strong><span>${data.members.length}</span></div>
              <div id="groupMemberList" class="group-member-list"></div>
            </div>
            <button id="leaveGroupButton" class="btn btn-outline full danger-outline" type="button">Ekipten ayrıl</button>
          </aside>
        </div>`;
      updateGroupModal(data, true);
      $('#groupModal').showModal();
      $('#groupMessageForm').addEventListener('submit', sendGroupMessage);
      $('#groupMessageText').addEventListener('keydown', event => {
        if (event.key === 'Enter' && !event.shiftKey) {
          event.preventDefault();
          $('#groupMessageForm').requestSubmit();
        }
      });
      bindGroupPhotoInput();
      $('#groupChatToggle')?.addEventListener('change', toggleGroupChat);
      $('#groupMemberList').addEventListener('click', handleGroupMemberClick);
      $('#groupMemberList').addEventListener('change', handleGroupMemberRoleChange);
      $('#groupMessageList').addEventListener('click', handleGroupMessageClick);
      $('#leaveGroupButton').addEventListener('click', leaveGroup);
      state.groupPoller = window.setInterval(() => { if (!state.groupSending) refreshGroupChat(false, true); }, 5000);
    } catch (e) { toast(e.message, 'error'); }
  }

  async function sendGroupMessage(event) {
    event.preventDefault();
    if (state.groupSending) return;

    const formElement = event.currentTarget;
    const groupId = Number(state.activeGroup?.group?.id || 0);
    const textarea = $('#groupMessageText', formElement);
    const fileInput = $('#groupPhotoInput', formElement);
    const bodyText = textarea?.value?.trim() || '';
    const selectedFile = fileInput?.files?.[0] || null;

    if (!groupId) {
      toast('Grup bilgisi bulunamadı. Sohbeti kapatıp yeniden açın.', 'error');
      return;
    }
    if (!bodyText && !selectedFile) {
      toast('Mesaj veya fotoğraf eklemelisiniz.', 'error');
      return;
    }

    const form = new FormData();
    form.append('action', 'send');
    form.append('group_id', String(groupId));
    if (bodyText) form.append('body', bodyText);
    if (selectedFile) form.append('image', selectedFile, selectedFile.name);

    state.groupSending = true;
    setGroupComposerState();
    try {
      await api('group_chat.php', {method:'POST', body:form});
      if (textarea) textarea.value = '';
      clearGroupPhotoInput();
      await refreshGroupChat(true);
    } catch (e) {
      toast(e.message, 'error');
    } finally {
      state.groupSending = false;
      setGroupComposerState();
      $('#groupMessageText')?.focus();
    }
  }

  async function toggleGroupChat(event) {
    const toggleElement = event.currentTarget;
    const enabled = toggleElement.checked;
    try {
      const data = await api('groups.php', {method:'POST', body:JSON.stringify({action:'toggle_chat', group_id:state.activeGroup.group.id, enabled})});
      toast(data.message);
      await Promise.all([refreshGroupChat(false), loadGroups()]);
    } catch (e) {
      toggleElement.checked = !enabled;
      toast(e.message, 'error');
    }
  }

  async function handleGroupMemberRoleChange(event) {
    const select = event.target.closest('[data-member-role]');
    if (!select) return;
    try {
      const data = await api('groups.php', {method:'POST', body:JSON.stringify({action:'set_role', group_id:state.activeGroup.group.id, user_id:Number(select.dataset.userId), role:select.value})});
      toast(data.message);
      await refreshGroupChat(false);
    } catch (e) { toast(e.message, 'error'); await refreshGroupChat(false, true); }
  }

  async function handleGroupMemberClick(event) {
    const button = event.target.closest('[data-member-action]');
    if (!button) return;
    const action = button.dataset.memberAction;
    const userId = Number(button.dataset.userId);
    if (action === 'direct_message') {
      await openDirectChat(Number(state.activeGroup.group.id), userId);
      return;
    }
    if (action === 'remove_member' && !window.confirm('Bu üyeyi ekipten çıkarmak istediğine emin misin?')) return;
    const payload = {action, group_id:state.activeGroup.group.id, user_id:userId};
    if (action === 'mute_member') payload.minutes = Number($(`[data-mute-duration="${userId}"]`)?.value || 60);
    try {
      const data = await api('groups.php', {method:'POST', body:JSON.stringify(payload)});
      toast(data.message);
      await Promise.all([refreshGroupChat(false), loadGroups()]);
    } catch (e) { toast(e.message, 'error'); }
  }

  async function handleGroupMessageClick(event) {
    const button = event.target.closest('[data-delete-group-message]');
    if (!button || !window.confirm('Bu mesaj silinsin mi?')) return;
    try {
      const data = await api('group_chat.php', {method:'POST', body:JSON.stringify({action:'delete_message', group_id:state.activeGroup.group.id, message_id:Number(button.dataset.deleteGroupMessage)})});
      toast(data.message);
      await refreshGroupChat(false);
    } catch (e) { toast(e.message, 'error'); }
  }

  async function leaveGroup() {
    if (!window.confirm('Bu ekipten ayrılmak istediğine emin misin?')) return;
    try {
      const data = await api('groups.php', {method:'POST', body:JSON.stringify({action:'leave', group_id:state.activeGroup.group.id})});
      toast(data.message);
      stopGroupPolling();
      $('#groupModal').close();
      state.activeGroup = null;
      await loadGroups();
    } catch (e) { toast(e.message, 'error'); }
  }

  function renderDirectConversations() {
    const list = $('#directConversationList');
    const count = $('#conversationCount');
    if (!list) return;
    if (!state.user) {
      list.innerHTML = '';
      if (count) count.textContent = '0 sohbet';
      return;
    }

    const conversations = state.directConversations || [];
    if (count) count.textContent = `${conversations.length} sohbet`;
    list.innerHTML = conversations.length ? conversations.map(item => {
      const canMessage = Number(item.can_message) === 1 || item.can_message === true;
      const disabledText = canMessage ? '' : 'Bu ekip bağlantısı artık aktif olmadığı için yeni mesaj gönderilemez.';
      return `<button class="conversation-card ${canMessage ? '' : 'is-disabled'}" type="button"
        data-open-conversation data-group-id="${item.group_id}" data-user-id="${item.peer_id}"
        ${canMessage ? '' : 'disabled'} title="${escapeHtml(disabledText)}">
        <span class="conversation-avatar">${escapeHtml(item.peer_name.slice(0,1).toLocaleUpperCase('tr-TR'))}</span>
        <span class="conversation-main">
          <span class="conversation-top"><strong>${escapeHtml(item.peer_name)}</strong><time>${item.last_message_at ? formatDate(item.last_message_at) : ''}</time></span>
          <span class="conversation-preview">${escapeHtml(item.last_preview || 'Henüz mesaj yok.')}</span>
          <span class="conversation-group">♧ ${escapeHtml(item.group_name)}${canMessage ? '' : ' · Sohbet kapalı'}</span>
        </span>
        <span class="conversation-arrow">›</span>
      </button>`;
    }).join('') : `<div class="conversation-empty">
      <strong>Henüz birebir konuşman yok</strong>
      <p>Bir ekibe girip üye listesinden “Özel mesaj” seçtiğinde konuşma burada görünür.</p>
    </div>`;
  }

  async function loadDirectConversations(silent = false) {
    if (!state.user) {
      state.directConversations = [];
      renderDirectConversations();
      return;
    }
    try {
      const data = await api('direct_chat.php?action=list');
      state.directConversations = data.conversations || [];
      renderDirectConversations();
    } catch (e) {
      if (!silent) toast(e.message, 'error');
    }
  }

  function stopDirectPolling() {
    if (state.directPoller) window.clearInterval(state.directPoller);
    state.directPoller = null;
  }

  function renderDirectMessages(data, forceBottom = false) {
    const list = $('#directMessageList');
    if (!list) return;
    const nearBottom = list.scrollHeight - list.scrollTop - list.clientHeight < 100;
    list.innerHTML = data.messages.length ? data.messages.map(message => {
      const mine = Number(message.sender_id) === Number(state.user?.id);
      return `<div class="group-message ${mine ? 'mine' : ''}">
        <div class="message-bubble">
          ${message.image_path ? `<a class="group-photo-link" href="${uploadUrl(message.image_path)}" target="_blank" rel="noopener"><img src="${uploadUrl(message.image_path)}" alt="${escapeHtml(message.username)} tarafından gönderilen özel fotoğraf"></a>` : ''}
          ${message.body ? `<p>${escapeHtml(message.body)}</p>` : ''}
          <div class="message-time"><span>${formatDate(message.created_at)}</span>${mine ? `<button data-delete-direct-message="${message.id}" title="Mesajı sil">Sil</button>` : ''}</div>
        </div>
      </div>`;
    }).join('') : '<div class="group-chat-empty">Henüz mesaj yok. İlk özel mesajı gönder.</div>';
    if (forceBottom || nearBottom) list.scrollTop = list.scrollHeight;
  }

  function setDirectComposerState() {
    const form = $('#directMessageForm');
    if (!form) return;
    const submit = $('button[type="submit"]', form);
    const file = $('#directPhotoInput', form);
    const textarea = $('#directMessageText', form);
    if (textarea) textarea.readOnly = state.directSending;
    if (submit) {
      submit.disabled = state.directSending;
      submit.textContent = state.directSending ? 'Gönderiliyor…' : 'Gönder';
    }
    if (file) file.disabled = state.directSending;
  }

  function bindDirectPhotoInput() {
    const input = $('#directPhotoInput');
    if (!input || input.dataset.bound === '1') return;
    input.dataset.bound = '1';
    input.addEventListener('change', event => {
      const file = event.target.files?.[0] || null;
      $('#directPhotoName').textContent = file ? file.name : 'Fotoğraf seçilmedi';
    });
  }

  function clearDirectPhotoInput() {
    const input = $('#directPhotoInput');
    if (input) {
      const replacement = input.cloneNode(true);
      replacement.value = '';
      replacement.removeAttribute('data-bound');
      input.replaceWith(replacement);
    }
    const label = $('#directPhotoName');
    if (label) label.textContent = 'Fotoğraf seçilmedi';
    bindDirectPhotoInput();
  }

  async function refreshDirectChat(forceBottom = false, silent = false) {
    const current = state.activeDirect;
    if (!current?.groupId || !current?.userId || (silent && state.directSending)) return;
    try {
      const data = await api(`direct_chat.php?group_id=${current.groupId}&user_id=${current.userId}`);
      if (!state.activeDirect || current.groupId !== state.activeDirect.groupId || current.userId !== state.activeDirect.userId) return;
      state.activeDirect.data = data;
      renderDirectMessages(data, forceBottom);
    } catch (e) {
      if (!silent) toast(e.message, 'error');
    }
  }

  async function openDirectChat(groupId, userId) {
    if (!requireLogin()) return;
    stopDirectPolling();
    try {
      const data = await api(`direct_chat.php?group_id=${Number(groupId)}&user_id=${Number(userId)}`);
      state.activeDirect = {groupId:Number(groupId), userId:Number(userId), data};
      $('#directMessageDetail').innerHTML = `
        <div class="direct-chat-heading">
          <div class="member-avatar">${escapeHtml(data.peer.username.slice(0,1).toLocaleUpperCase('tr-TR'))}</div>
          <div><span class="eyebrow">${escapeHtml(data.group.name)}</span><h2>${escapeHtml(data.peer.username)}</h2><p>Bu sohbet yalnızca aynı ekipte aktif olduğunuz sürece kullanılabilir.</p></div>
        </div>
        <div id="directMessageList" class="group-message-list direct-message-list" aria-live="polite"></div>
        <form id="directMessageForm" class="group-composer" enctype="multipart/form-data" autocomplete="off">
          <textarea id="directMessageText" name="body" maxlength="1000" rows="2" placeholder="Özel mesaj yaz…"></textarea>
          <div class="composer-actions">
            <label class="photo-picker">📷 Fotoğraf<input id="directPhotoInput" type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
            <span id="directPhotoName" class="selected-file">Fotoğraf seçilmedi</span>
            <button class="btn btn-primary" type="submit">Gönder</button>
          </div>
        </form>`;
      renderDirectMessages(data, true);
      const dialog = $('#directMessageModal');
      if (!dialog.open) dialog.showModal();
      $('#directMessageForm').addEventListener('submit', sendDirectMessage);
      $('#directMessageText').addEventListener('keydown', event => {
        if (event.key === 'Enter' && !event.shiftKey) {
          event.preventDefault();
          $('#directMessageForm').requestSubmit();
        }
      });
      bindDirectPhotoInput();
      $('#directMessageList').addEventListener('click', handleDirectMessageClick);
      state.directPoller = window.setInterval(() => {
        if (!state.directSending && !document.hidden) refreshDirectChat(false, true);
      }, 4000);
      $('#directMessageText').focus();
    } catch (e) {
      toast(e.message, 'error');
    }
  }

  async function sendDirectMessage(event) {
    event.preventDefault();
    if (state.directSending || !state.activeDirect) return;
    const formElement = event.currentTarget;
    const textarea = $('#directMessageText', formElement);
    const fileInput = $('#directPhotoInput', formElement);
    const bodyText = textarea?.value?.trim() || '';
    const selectedFile = fileInput?.files?.[0] || null;
    if (!bodyText && !selectedFile) {
      toast('Mesaj veya fotoğraf eklemelisiniz.', 'error');
      return;
    }

    const form = new FormData();
    form.append('action', 'send');
    form.append('group_id', String(state.activeDirect.groupId));
    form.append('user_id', String(state.activeDirect.userId));
    if (bodyText) form.append('body', bodyText);
    if (selectedFile) form.append('image', selectedFile, selectedFile.name);

    state.directSending = true;
    setDirectComposerState();
    try {
      await api('direct_chat.php', {method:'POST', body:form});
      if (textarea) textarea.value = '';
      clearDirectPhotoInput();
      await refreshDirectChat(true);
      await loadDirectConversations(true);
    } catch (e) {
      toast(e.message, 'error');
    } finally {
      state.directSending = false;
      setDirectComposerState();
      $('#directMessageText')?.focus();
    }
  }

  async function handleDirectMessageClick(event) {
    const button = event.target.closest('[data-delete-direct-message]');
    if (!button || !state.activeDirect || !window.confirm('Bu özel mesaj silinsin mi?')) return;
    try {
      const payload = {
        action:'delete_message',
        group_id:state.activeDirect.groupId,
        user_id:state.activeDirect.userId,
        message_id:Number(button.dataset.deleteDirectMessage)
      };
      const data = await api('direct_chat.php', {method:'POST', body:JSON.stringify(payload)});
      toast(data.message);
      await refreshDirectChat(false);
      await loadDirectConversations(true);
    } catch (e) {
      toast(e.message, 'error');
    }
  }

  function initMap() {
    if (!window.L) {
      $('#mapCanvas').innerHTML = '<div class="empty-state">Harita kütüphanesi yüklenemedi. İnternet bağlantısını kontrol edin.</div>';
      return;
    }
    if (!state.map) {
      state.map = L.map('mapCanvas').setView([40.9865, 29.0275], 14);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom:19, attribution:'© OpenStreetMap katkıcıları'}).addTo(state.map);
    }
    updateMapMarkers();
    setTimeout(() => state.map.invalidateSize(), 100);
  }

  function updateMapMarkers() {
    if (!state.map) return;
    state.markers.forEach(marker => marker.remove());
    state.markers = [];
    const active = state.missions.filter(m => Number(m.is_map_active) === 1 && m.latitude && m.longitude);
    active.forEach(m => {
      const marker = L.marker([Number(m.latitude), Number(m.longitude)]).addTo(state.map);
      marker.bindPopup(`<strong>${escapeHtml(m.category_icon)} ${escapeHtml(m.title)}</strong><br>${escapeHtml(m.location_name)}<br><small>${escapeHtml(missionStatusLabel(m))} · ${formatDate(m.expires_at)}</small><br><button onclick="window.etkiOpenMission(${Number(m.id)})" style="margin-top:8px">Görevi aç</button>`);
      state.markers.push(marker);
    });
    const created = active.find(m => Number(m.id) === Number(state.justCreatedMissionId));
    if (created) {
      state.map.setView([Number(created.latitude), Number(created.longitude)], 16);
      state.mapHasFit = true;
    } else if (!state.mapHasFit && active.length) {
      const bounds = L.latLngBounds(active.map(m => [Number(m.latitude), Number(m.longitude)]));
      state.map.fitBounds(bounds.pad(0.2), {maxZoom:15});
      state.mapHasFit = true;
    }
  }
  window.etkiOpenMission = openMission;

  async function loadProfile() {
    try {
      const data = await api('profile.php');
      const u = data.user;
      const trustLabel = Number(u.trust_score) >= 85 ? 'Yüksek güven' : Number(u.trust_score) >= 70 ? 'Güvenilir' : Number(u.trust_score) >= 50 ? 'Gelişiyor' : 'Yeni';
      $('#profileContent').innerHTML = `
        <div class="profile-header"><div class="profile-identity"><div class="profile-avatar">${escapeHtml(u.username.slice(0,1).toLocaleUpperCase('tr-TR'))}</div><div><span class="eyebrow">${trustLabel}</span><h2>${escapeHtml(u.username)}</h2><p>${escapeHtml(u.email)} · ${u.xp} XP</p></div></div><div class="profile-level"><small>SEVİYE</small><strong>${u.level}</strong><span>${u.energy} enerji</span></div></div>
        <div class="stats-grid"><div class="stat-card"><span>Gönderilen kanıt</span><strong>${data.stats.submitted}</strong><small>Doğrulama adayı</small></div><div class="stat-card"><span>Doğrulanmış görev</span><strong>${data.stats.completed}</strong><small>Gerçek etki</small></div><div class="stat-card"><span>Teşekkür</span><strong>${data.stats.thanks}</strong><small>Topluluk katkısı</small></div><div class="stat-card"><span>Güven puanı</span><strong>${u.trust_score}</strong><small>${trustLabel}</small></div></div>
        <div class="content-grid two-column"><div class="panel"><div class="panel-head"><h2>Rozetler</h2></div><div class="badge-grid">${data.badges.length ? data.badges.map(b => `<div class="badge-card"><span>${escapeHtml(b.icon)}</span><strong>${escapeHtml(b.name)}</strong><small>${formatDate(b.earned_at)}</small></div>`).join('') : '<p>Henüz rozet yok.</p>'}</div></div><div class="panel"><div class="panel-head"><h2>XP geçmişi</h2></div><div class="xp-list">${data.xp_history.length ? data.xp_history.map(x => `<div class="xp-row"><div><strong>${escapeHtml(x.reason)}</strong><small>${escapeHtml(x.mission_title || '')}</small></div><b>+${x.amount}</b></div>`).join('') : '<p>Henüz XP hareketi yok.</p>'}</div></div></div>
        <div style="margin-top:16px"><button class="btn btn-danger" id="logoutBtn">Çıkış yap</button></div>`;
      $('#logoutBtn').addEventListener('click', logout);
    } catch (e) { toast(e.message, 'error'); }
  }

  async function loadAdmin() {
    try {
      const data = await api('admin.php');
      const s = data.stats;
      $('#adminStats').innerHTML = [
        ['Kullanıcı', s.users], ['Açık görev', s.open_missions], ['Tamamlanan', s.completed_missions], ['Bekleyen kanıt', s.pending_evidence]
      ].map(([l,v]) => `<div class="stat-card"><span>${l}</span><strong>${v}</strong><small>Canlı veritabanı</small></div>`).join('');
      $('#adminUsers').innerHTML = data.users.map(u => `<tr><td>${escapeHtml(u.username)}<br><small>${escapeHtml(u.email)}</small></td><td>${escapeHtml(u.role)}</td><td>${u.level}</td><td>${u.trust_score}</td></tr>`).join('');
      $('#adminMissions').innerHTML = data.missions.map(m => `<tr><td>${escapeHtml(m.title)}<br><small>${escapeHtml(m.creator_name)}</small></td><td>${statusLabel(m.status)}</td><td>${escapeHtml(m.risk_level)}</td><td><select class="admin-status" data-id="${m.id}"><option value="">Değiştir</option><option value="open">Açık</option><option value="completed">Tamamlandı</option><option value="rejected">Reddedildi</option><option value="safety_closed">Güvenlik nedeniyle kapat</option></select></td></tr>`).join('');
      $$('.admin-status').forEach(select => select.addEventListener('change', async () => {
        if (!select.value) return;
        try {
          const result = await api('admin.php', {method:'POST', body:JSON.stringify({action:'moderate_mission', mission_id:Number(select.dataset.id), status:select.value})});
          toast(result.message); await refreshData(); await loadAdmin();
        } catch (e) { toast(e.message, 'error'); }
      }));
    } catch (e) { toast(e.message, 'error'); }
  }

  async function logout() {
    try {
      const data = await api('auth.php?action=logout', {method:'POST', body:JSON.stringify({})});
      state.user = null; state.csrf = '';
      toast(data.message); renderAuth(); openSection('home');
      await loadAuth();
    } catch (e) { toast(e.message, 'error'); }
  }

  async function loadAuth() {
    const data = await api('auth.php?action=me');
    state.user = data.user;
    state.csrf = data.csrf_token;
    renderAuth();
  }
  async function loadCategories() { state.categories = (await api('categories.php')).categories; renderCategories(); }
  async function loadMissions() { state.missions = (await api('missions.php')).missions; renderMissions(); renderStats(); renderImpact(); }
  async function loadGroups() { state.groups = (await api('groups.php')).groups; renderGroups(); renderStats(); }
  async function refreshData() {
    const jobs = [loadMissions(), loadGroups()];
    if (state.user) jobs.push(loadDirectConversations(true));
    await Promise.all(jobs);
  }

  function expiryFromOption(option) {
    const d = new Date();
    const amounts = { '2h': 2*3600000, '1d': 86400000, '3d': 3*86400000, '7d': 7*86400000 };
    return new Date(d.getTime() + (amounts[option] || amounts['7d'])).toISOString().slice(0,19).replace('T',' ');
  }

  function bindEvents() {
    $$('[data-section]').forEach(el => el.addEventListener('click', e => { e.preventDefault(); openSection(el.dataset.section); }));
    $$('[data-open-auth]').forEach(el => el.addEventListener('click', () => $('#authModal').showModal()));
    $$('[data-close-dialog]').forEach(el => el.addEventListener('click', () => el.closest('dialog').close()));
    $$('[data-open-create]').forEach(el => el.addEventListener('click', () => { if (requireLogin()) $('#createMissionModal').showModal(); }));
    $$('[data-create-category]').forEach(el => el.addEventListener('click', () => {
      if (!requireLogin()) return;
      const category = state.categories.find(c => c.slug === el.dataset.createCategory);
      if (category) $('#missionCategory').value = category.id;
      updateMissionForm();
      $('#createMissionModal').showModal();
    }));

    $('#authButton').addEventListener('click', () => state.user ? openSection('profile') : $('#authModal').showModal());
    $('#mobileMenu').addEventListener('click', () => $('#sidebar').classList.toggle('open'));
    $('#refreshBtn').addEventListener('click', async () => { await refreshData(); toast('Veriler yenilendi.'); });
    $('#globalSearch').addEventListener('input', e => { state.search = e.target.value; renderMissions(); if (state.search) openSection('missions'); });
    $('#openTeamModal').addEventListener('click', () => { if (requireLogin()) $('#teamModal').showModal(); });
    $('#refreshConversations')?.addEventListener('click', () => {
      if (requireLogin()) loadDirectConversations();
    });
    $('#directConversationList')?.addEventListener('click', event => {
      const button = event.target.closest('[data-open-conversation]');
      if (!button || button.disabled) return;
      openDirectChat(Number(button.dataset.groupId), Number(button.dataset.userId));
    });
    $('#missionCategory').addEventListener('change', updateMissionForm);
    $('#useCurrentLocation').addEventListener('click', useCurrentLocation);
    $('#findAddressLocation').addEventListener('click', findAddressLocation);
    $('#missionAddressSearch').addEventListener('keydown', event => {
      if (event.key === 'Enter') {
        event.preventDefault();
        findAddressLocation();
      }
    });
    $('#addressSearchResults').addEventListener('click', selectAddressResult);
    $('#groupModal').addEventListener('close', () => { stopGroupPolling(); state.activeGroup = null; });
    $('#directMessageModal').addEventListener('close', () => { stopDirectPolling(); state.activeDirect = null; });

    $$('[data-auth-tab]').forEach(btn => btn.addEventListener('click', () => {
      $$('[data-auth-tab]').forEach(b => b.classList.toggle('active', b === btn));
      $('#loginForm').classList.toggle('hidden', btn.dataset.authTab !== 'login');
      $('#registerForm').classList.toggle('hidden', btn.dataset.authTab !== 'register');
    }));

    $('#loginForm').addEventListener('submit', async event => {
      event.preventDefault();
      const form = new FormData(event.currentTarget);
      try {
        const data = await api('auth.php?action=login', {method:'POST', body:JSON.stringify(Object.fromEntries(form))});
        state.user = data.user; state.csrf = data.csrf_token; renderAuth(); $('#authModal').close(); toast(data.message); await refreshData();
      } catch (e) { toast(e.message, 'error'); }
    });

    $('#registerForm').addEventListener('submit', async event => {
      event.preventDefault();
      const form = new FormData(event.currentTarget);
      try {
        const data = await api('auth.php?action=register', {method:'POST', body:JSON.stringify(Object.fromEntries(form))});
        state.user = data.user; state.csrf = data.csrf_token; renderAuth(); $('#authModal').close(); toast(data.message); await refreshData();
      } catch (e) { toast(e.message, 'error'); }
    });

    $('#createMissionForm').addEventListener('submit', async event => {
      event.preventDefault();
      const formElement = event.currentTarget;
      const submitButton = $('#publishMissionButton');
      if (submitButton.disabled) return;
      const form = new FormData(formElement);
      const payload = Object.fromEntries(form);
      const selectedSlug = $('#missionCategory').selectedOptions[0]?.dataset.slug || '';
      payload.expires_at = expiryFromOption(payload.expires_option);
      delete payload.expires_option;

      if (selectedSlug !== 'animal' && String(payload.location_name || '').trim().length < 2) {
        toast('Bu görev türü için konum veya genel bölge bilgisini yazmalısın.', 'error');
        $('#missionLocationName').focus();
        return;
      }
      if (selectedSlug !== 'animal' && (!payload.latitude || !payload.longitude)) {
        toast('Görevin haritada görünmesi için şu anki konumunu ekle veya açık adresi haritada bul.', 'error');
        $('#useCurrentLocation').focus();
        return;
      }

      submitButton.disabled = true;
      submitButton.textContent = 'Yayınlanıyor…';
      try {
        const data = await api('missions.php', {method:'POST', body:JSON.stringify(payload)});
        state.justCreatedMissionId = Number(data.mission_id);
        if (data.mission) {
          state.missions = [data.mission, ...state.missions.filter(m => Number(m.id) !== Number(data.mission.id))];
        }
        state.filter = 'all';
        state.search = '';
        $('#globalSearch').value = '';
        $$('.filter-pill').forEach(btn => btn.classList.toggle('active', btn.dataset.filter === 'all'));
        renderMissions(); renderStats(); renderImpact();
        formElement.reset();
        clearCapturedLocation();
        updateMissionForm();
        $('#createMissionModal').close();
        openSection('missions');
        window.scrollTo({top: 0, behavior: 'smooth'});
        toast(data.message || 'Görev yayınlandı ve listeye eklendi.');

        try {
          await loadMissions();
          if (!state.missions.some(m => Number(m.id) === Number(data.mission_id)) && data.mission) {
            state.missions.unshift(data.mission);
            renderMissions(); renderStats(); renderImpact();
          }
        } catch (refreshError) {
          if (!data.mission) throw refreshError;
        }
        setTimeout(() => { state.justCreatedMissionId = null; renderMissions(); }, 1800);
      } catch (e) {
        toast(e.message, 'error');
      } finally {
        submitButton.disabled = false;
        submitButton.textContent = 'Görevi yayınla';
      }
    });

    $('#completeForm').addEventListener('submit', async event => {
      event.preventDefault();
      const formElement = event.currentTarget;
      const form = new FormData(formElement);
      form.append('action', 'complete');
      try {
        const data = await api('mission_action.php', {method:'POST', body:form});
        toast(data.message); formElement.reset(); $('#completeModal').close(); $('#missionModal').close(); await refreshData();
      } catch (e) { toast(e.message, 'error'); }
    });

    $('#verifyForm').addEventListener('submit', async event => {
      event.preventDefault();
      const payload = Object.fromEntries(new FormData(event.currentTarget));
      payload.action = 'verify';
      try {
        const data = await api('mission_action.php', {method:'POST', body:JSON.stringify(payload)});
        toast(data.message); $('#verifyModal').close(); $('#missionModal').close(); await refreshData();
      } catch (e) { toast(e.message, 'error'); }
    });

    $('#teamForm').addEventListener('submit', async event => {
      event.preventDefault();
      const formElement = event.currentTarget;
      const payload = Object.fromEntries(new FormData(formElement));
      try {
        const data = await api('groups.php', {method:'POST', body:JSON.stringify(payload)});
        toast(data.message); formElement.reset(); $('#teamModal').close(); await loadGroups();
        if (data.group_id) await openGroup(data.group_id);
      } catch (e) { toast(e.message, 'error'); }
    });
  }

  const missionFormConfig = {
    question: {
      hint: 'Bir yer hakkında kısa süreli ve güncel bilgi iste. Görevin haritada görünmesi için mevcut konum veya açık adres seçilir.',
      title: 'Örn. Kütüphanede prizli boş masa var mı?',
      description: 'Hangi güncel bilgiyi öğrenmek istediğini açıkça yaz.',
      locationLabel: 'Yer / kurum adı',
      locationPlaceholder: 'Kütüphane, durak, mağaza veya alan adı',
      locationHelp: 'Cevap verecek kişinin doğru yeri anlayabilmesi için gereklidir.',
      locationRequired: true,
      useCoordinates: true
    },
    cleaning: {
      hint: 'Yalnızca küçük ve güvenli temizlik görevleri oluştur. Kesici, tıbbi veya kimyasal atıklar görev olamaz.',
      title: 'Örn. Park girişindeki güvenli ambalajları temizle',
      description: 'Toplanacak güvenli atıkları, sınırları ve beklenen sonucu yaz.',
      locationLabel: 'Genel alan',
      locationPlaceholder: 'Park, sahil veya mahalle adı',
      locationHelp: 'Görevin haritada görünmesi için mevcut konumunu ekle veya açık adresi bul.',
      locationRequired: true,
      useCoordinates: true
    },
    accessibility: {
      hint: 'Rampa, asansör, kaldırım veya engelsiz giriş gibi erişilebilirlik bilgisini doğrulat.',
      title: 'Örn. Metro asansörü çalışıyor mu?',
      description: 'Kontrol edilmesini istediğin erişilebilirlik unsurunu belirt.',
      locationLabel: 'Yer / giriş adı',
      locationPlaceholder: 'İstasyon, bina veya giriş adı',
      locationHelp: 'Genel yer adını yaz ve mevcut konum ya da açık adresle harita noktasını ekle.',
      locationRequired: true,
      useCoordinates: true
    },
    municipal: {
      hint: 'Kişiyi veya aracı teşhir etmeden kamusal sorunu doğru kuruma yönlendirmeye odaklan.',
      title: 'Örn. Kaldırım çökmesini doğru kuruma yönlendir',
      description: 'Sorunun etkisini ve hangi kamusal alanı etkilediğini kişisel bilgi vermeden yaz.',
      locationLabel: 'Mahalle / kamusal alan',
      locationPlaceholder: 'Mahalle, cadde veya park adı',
      locationHelp: 'Açık adresi aratabilir veya mevcut konumunu ekleyebilirsin; halka yaklaşık alan gösterilir.',
      locationRequired: true,
      useCoordinates: true
    },
    animal: {
      hint: 'Hayvan görevinde genel bölge isteğe bağlıdır. Haritaya eklersen sistem kesin noktayı yaklaşık 1 km düzeyinde gizler.',
      title: 'Örn. Doğrulanmış su noktasını kontrol et',
      description: 'Güvenli kontrolü ve yapılmaması gereken müdahaleleri açıkça yaz.',
      locationLabel: 'Genel bölge (isteğe bağlı)',
      locationPlaceholder: 'Örn. Yoğurtçu Parkı çevresi',
      locationHelp: 'Kesin nokta herkese gösterilmez. Mevcut konum veya adres seçersen yaklaşık bölge olarak kaydedilir.',
      locationRequired: false,
      useCoordinates: true
    },
    solution_check: {
      hint: 'Kurumun çözüldü dediği bir sorunun sahadaki güncel durumunu doğrulat.',
      title: 'Örn. Sokak lambası gerçekten onarıldı mı?',
      description: 'Neyin çözüldüğünün bildirildiğini ve güvenli şekilde neyin kontrol edileceğini yaz.',
      locationLabel: 'Kontrol alanı',
      locationPlaceholder: 'Cadde, park veya tesis adı',
      locationHelp: 'Mevcut konum veya açık adresle harita noktası eklenir; halka yaklaşık alan gösterilir.',
      locationRequired: true,
      useCoordinates: true
    }
  };

  function updateMissionForm() {
    const option = $('#missionCategory').selectedOptions[0];
    const slug = option?.dataset.slug || 'question';
    const config = missionFormConfig[slug] || missionFormConfig.question;
    $$('.question-only').forEach(el => el.classList.toggle('hidden', slug !== 'question'));
    $$('.animal-only').forEach(el => el.classList.toggle('hidden', slug !== 'animal'));
    $('#categoryHint').textContent = config.hint;
    $('#missionTitle').placeholder = config.title;
    $('#missionDescription').placeholder = config.description;
    $('#missionLocationLabelText').textContent = config.locationLabel;
    $('#missionLocationName').placeholder = config.locationPlaceholder;
    $('#missionLocationName').required = config.locationRequired;
    $('#missionLocationHelp').textContent = config.locationHelp;
    $('#missionLocationTools').classList.toggle('hidden', !config.useCoordinates);
    const status = $('#locationStatus');
    if (!$('#missionLatitude').value) {
      status.textContent = slug === 'animal' ? 'Harita noktası isteğe bağlıdır; eklenirse yaklaşık gösterilir.' : 'Haritada görünmesi için mevcut konum veya açık adres seçmelisin.';
      status.className = 'location-status';
    }
  }

  function clearCapturedLocation() {
    $('#missionLatitude').value = '';
    $('#missionLongitude').value = '';
    $('#missionAddressSearch').value = '';
    $('#addressSearchResults').innerHTML = '';
    $('#addressSearchResults').classList.add('hidden');
    const status = $('#locationStatus');
    status.textContent = 'Harita noktası henüz eklenmedi.';
    status.className = 'location-status';
  }

  function setCapturedLocation(latitude, longitude, label = '', source = 'Konum') {
    const lat = Number(latitude);
    const lon = Number(longitude);
    if (!Number.isFinite(lat) || !Number.isFinite(lon)) {
      toast('Harita konumu geçersiz geldi.', 'error');
      return;
    }
    $('#missionLatitude').value = lat.toFixed(7);
    $('#missionLongitude').value = lon.toFixed(7);
    const selectedSlug = $('#missionCategory').selectedOptions[0]?.dataset.slug || '';
    const safeLabel = String(label || '').trim();
    const currentLabel = $('#missionLocationName').value.trim();
    const generatedLabel = ['Mevcut konum çevresi', 'Yaklaşık hayvan destek bölgesi'].includes(currentLabel);
    if (selectedSlug === 'animal') {
      if (!currentLabel || generatedLabel) $('#missionLocationName').value = 'Yaklaşık hayvan destek bölgesi';
    } else if (safeLabel && (!currentLabel || generatedLabel || source === 'Adres')) {
      $('#missionLocationName').value = safeLabel.slice(0, 150);
    } else if (!currentLabel) {
      $('#missionLocationName').value = 'Mevcut konum çevresi';
    }
    const status = $('#locationStatus');
    status.textContent = selectedSlug === 'animal'
      ? `${source} eklendi. Haritada kesin nokta yerine yaklaşık bölge gösterilecek.`
      : `${source} eklendi. Görev aktif olduğu sürece haritada görünecek.`;
    status.className = 'location-status success';
    $('#addressSearchResults').classList.add('hidden');
  }

  async function findAddressLocation() {
    if (state.addressSearching) return;
    const query = $('#missionAddressSearch').value.trim();
    if (query.length < 4) {
      toast('Mahalle, cadde, ilçe ve şehir içeren daha açık bir adres yaz.', 'error');
      $('#missionAddressSearch').focus();
      return;
    }
    const button = $('#findAddressLocation');
    const results = $('#addressSearchResults');
    state.addressSearching = true;
    button.disabled = true;
    button.textContent = 'Aranıyor…';
    results.classList.remove('hidden');
    results.innerHTML = '<div class="address-loading">Adres aranıyor…</div>';
    try {
      const data = await api(`geocode.php?q=${encodeURIComponent(query)}`);
      if (!data.results?.length) {
        results.innerHTML = '<div class="address-loading">Sonuç bulunamadı. İlçe ve şehir ekleyerek tekrar dene.</div>';
        return;
      }
      results.innerHTML = data.results.map((item, index) =>
        `<button type="button" class="address-result" data-address-result
          data-lat="${Number(item.latitude)}" data-lon="${Number(item.longitude)}"
          data-label="${encodeURIComponent(item.label)}">
          <strong>${index + 1}. adres sonucu</strong><span>${escapeHtml(item.label)}</span>
        </button>`
      ).join('');
    } catch (e) {
      results.innerHTML = `<div class="address-loading error">${escapeHtml(e.message)}</div>`;
    } finally {
      state.addressSearching = false;
      button.disabled = false;
      button.textContent = 'Adresi haritada bul';
    }
  }

  function selectAddressResult(event) {
    const button = event.target.closest('[data-address-result]');
    if (!button) return;
    const label = decodeURIComponent(button.dataset.label || '');
    $('#missionAddressSearch').value = label;
    setCapturedLocation(button.dataset.lat, button.dataset.lon, label, 'Adres');
  }

  function useCurrentLocation() {
    const status = $('#locationStatus');
    if (!navigator.geolocation) {
      status.textContent = 'Tarayıcın konum özelliğini desteklemiyor.';
      status.className = 'location-status error';
      return;
    }
    status.textContent = 'Konum alınıyor…';
    status.className = 'location-status';
    navigator.geolocation.getCurrentPosition(async position => {
      const lat = Number(position.coords.latitude);
      const lon = Number(position.coords.longitude);
      setCapturedLocation(lat, lon, '', 'Mevcut konum');
      try {
        const data = await api(`geocode.php?lat=${encodeURIComponent(lat)}&lon=${encodeURIComponent(lon)}`);
        const label = data.result?.label || '';
        if (label) setCapturedLocation(lat, lon, label, 'Mevcut konum');
      } catch (_) {
        // Ters adres bulunamasa bile koordinatlar geçerlidir.
      }
    }, error => {
      const message = error?.code === 1
        ? 'Konum izni reddedildi. Açık adresle harita noktası ekleyebilirsin.'
        : 'Konum alınamadı. Açık adresle harita noktası ekleyebilirsin.';
      status.textContent = message;
      status.className = 'location-status error';
    }, {enableHighAccuracy: true, timeout: 12000, maximumAge: 30000});
  }

  async function boot() {
    bindEvents();
    try {
      await loadAuth();
      const initialJobs = [loadCategories(), loadMissions(), loadGroups()];
      if (state.user) initialJobs.push(loadDirectConversations(true));
      await Promise.all(initialJobs);
      renderStats(); renderImpact();
      if (!state.missionPoller) {
        state.missionPoller = window.setInterval(() => {
          if (!document.hidden && !state.groupSending && !state.directSending) loadMissions().catch(() => {});
        }, 30000);
      }
    } catch (e) {
      toast(`${e.message} Veritabanını içe aktardığınızdan emin olun.`, 'error');
    }
  }

  boot();
})();
