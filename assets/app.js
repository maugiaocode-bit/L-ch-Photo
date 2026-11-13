/* Lịch Chụp – giao diện (vanilla JS, không cần build) */
(() => {
'use strict';

/* ============ Tiện ích ============ */
const $ = (s, r = document) => r.querySelector(s);
const pad = n => String(n).padStart(2, '0');
const ymd = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const hm = d => `${pad(d.getHours())}:${pad(d.getMinutes())}`;
const parseDT = s => { const m = s.match(/(\d+)-(\d+)-(\d+)[ T](\d+):(\d+)/); return new Date(+m[1], m[2] - 1, +m[3], +m[4], +m[5]); };
const addDays = (d, n) => { const x = new Date(d); x.setDate(x.getDate() + n); return x; };
const sod = d => new Date(d.getFullYear(), d.getMonth(), d.getDate());
const mondayOf = d => addDays(sod(d), -((d.getDay() + 6) % 7));
const WD = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];
const WDF = ['Chủ nhật', 'Thứ hai', 'Thứ ba', 'Thứ tư', 'Thứ năm', 'Thứ sáu', 'Thứ bảy'];
const E = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const isMobile = () => window.innerWidth <= 860;
const ROLES = { photographer: 'Thợ chụp', assistant: 'Thợ phụ', makeup: 'Makeup' };
const ROLE_COLOR = { photographer: '#0a84ff', assistant: '#af52de', makeup: '#ff375f' };
const STATUS = { pending: 'Chờ phản hồi', accepted: 'Đã đồng ý', declined: 'Từ chối' };

const ICON = {
  cal: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="4.5" width="17" height="16" rx="3.5"/><path d="M8 2.8v3.4M16 2.8v3.4M3.5 9.5h17"/></svg>',
  users: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8.5" r="3.5"/><path d="M2.8 19.5c.6-3.3 3-5 6.2-5s5.6 1.7 6.2 5"/><path d="M16 5.3a3.4 3.4 0 010 6.4M18.5 14.9c1.6.7 2.6 2.2 2.9 4.2"/></svg>',
  gear: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3.2"/><path d="M19.4 14.5a1.7 1.7 0 00.3 1.9l.1.1a2 2 0 11-2.8 2.8l-.1-.1a1.7 1.7 0 00-1.9-.3 1.7 1.7 0 00-1 1.5V21a2 2 0 11-4 0v-.1a1.7 1.7 0 00-1.1-1.5 1.7 1.7 0 00-1.9.3l-.1.1a2 2 0 11-2.8-2.8l.1-.1a1.7 1.7 0 00.3-1.9 1.7 1.7 0 00-1.5-1H3a2 2 0 110-4h.1a1.7 1.7 0 001.5-1.1 1.7 1.7 0 00-.3-1.9l-.1-.1a2 2 0 112.8-2.8l.1.1a1.7 1.7 0 001.9.3h0a1.7 1.7 0 001-1.5V3a2 2 0 114 0v.1a1.7 1.7 0 001 1.5 1.7 1.7 0 001.9-.3l.1-.1a2 2 0 112.8 2.8l-.1.1a1.7 1.7 0 00-.3 1.9v0a1.7 1.7 0 001.5 1H21a2 2 0 110 4h-.1a1.7 1.7 0 00-1.5 1z"/></svg>',
  shield: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l8 3v6c0 4.5-3.2 7.8-8 9-4.8-1.2-8-4.5-8-9V6z"/><path d="M9 12l2.2 2.2L15.5 10"/></svg>',
  plus: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>',
  left: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7"/></svg>',
  right: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5l7 7-7 7"/></svg>',
  cam: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h3l1.6-2.4h6.8L17 8h3a1 1 0 011 1v9a1 1 0 01-1 1H4a1 1 0 01-1-1V9a1 1 0 011-1z"/><circle cx="12" cy="13" r="3.6"/></svg>',
  out: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><path d="M15 4h3a2 2 0 012 2v12a2 2 0 01-2 2h-3M10 16l-4-4 4-4M6 12h10"/></svg>',
};

/* ============ Trạng thái ============ */
const S = { ready: false, user: null, csrf: '', canRegister: true, authMode: 'login', page: 'calendar', view: 'month', cur: new Date(), mini: new Date(), staff: [], shoots: [], tgScroll: null, error: '', adminUsers: [] };
const app = $('#app');

async function api(route, { body, query, method } = {}) {
  const qs = new URLSearchParams({ r: route, ...(query || {}) });
  let res, data;
  try {
    res = await fetch('api/index.php?' + qs, {
      method: method || (body ? 'POST' : 'GET'), credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF': S.csrf },
      body: body ? JSON.stringify(body) : undefined,
    });
    data = await res.json();
  } catch (e) {
    throw new Error('Không kết nối được máy chủ (api/index.php). Hãy kiểm tra PHP và cấu hình database.');
  }
  if (!res.ok) {
    if (res.status === 401 && S.user) { S.user = null; render(); }
    throw new Error(data.error || 'Có lỗi xảy ra');
  }
  return data;
}

function toast(msg, bad) {
  const t = document.createElement('div');
  t.className = 'toast' + (bad ? ' bad' : '');
  t.textContent = msg;
  $('#toast-root').appendChild(t);
  setTimeout(() => t.remove(), 3600);
}

/* ============ Dữ liệu ============ */
function range() {
  const c = S.cur;
  if (S.view === 'month') { const s = mondayOf(new Date(c.getFullYear(), c.getMonth(), 1)); return [s, addDays(s, 42)]; }
  if (S.view === 'week') { const s = isMobile() ? sod(c) : mondayOf(c); return [s, addDays(s, isMobile() ? 3 : 7)]; }
  if (S.view === 'day') { const s = sod(c); return [s, addDays(s, 1)]; }
  return [sod(c), addDays(sod(c), 60)];
}
async function loadShoots() {
  const [a, b] = range();
  const r = await api('shoots_list', { query: { from: ymd(a) + ' 00:00', to: ymd(b) + ' 00:00' } });
  S.shoots = r.shoots;
}
async function loadStaff() { S.staff = (await api('staff_list')).staff; }
async function refresh() {
  try { await Promise.all([loadShoots(), loadStaff()]); } catch (e) { toast(e.message, true); }
  render();
}
function shootStatus(s) {
  if (!s.staff.length) return 'none';
  if (s.staff.some(x => x.status === 'declined')) return 'declined';
  if (s.staff.every(x => x.status === 'accepted')) return 'ok';
  return 'pending';
}
const statusText = { none: 'Chưa gán nhân sự', ok: 'Đã xác nhận đủ', pending: 'Chờ phản hồi', declined: 'Có người từ chối' };

/* ============ Render chính ============ */
function render() {
  if (!S.ready) { app.innerHTML = `<div class="auth"><div class="box glass">${S.error ? `<div class="err">${E(S.error)}</div>` : 'Đang tải…'}</div></div>`; return; }
  if (!S.user) return renderAuth();
  const prevBody = $('.tg-body'); if (prevBody) S.tgScroll = prevBody.scrollTop;
  const cal = S.page === 'calendar';
  app.innerHTML = `<div class="shell">${sidebar()}<main class="main glass">${topbar()}<div class="content">${pageHTML()}</div></main></div>
    <nav class="tabbar glass">${tab('calendar', 'cal', 'Lịch')}${tab('staff', 'users', 'Nhân sự')}${tab('settings', 'gear', 'Tài khoản')}${S.user.is_admin ? tab('admin', 'shield', 'Admin') : ''}</nav>
    ${cal || S.page === 'staff' ? `<button class="fab" data-act="${cal ? 'new' : 'staffadd'}" aria-label="Thêm">${ICON.plus}</button>` : ''}`;
  const ms = $('.mstrip .on'); if (ms) { const st = ms.parentElement; st.scrollLeft = ms.offsetLeft - st.clientWidth / 2 + ms.offsetWidth / 2; }
  const tb = $('.tg-body');
  if (tb) {
    const head = $('.tg-head'); if (head) head.style.paddingRight = (tb.offsetWidth - tb.clientWidth) + 'px';
    tb.scrollTop = S.tgScroll ?? defaultScroll();
    tb.addEventListener('scroll', () => { S.tgScroll = tb.scrollTop; });
  }
}
const defaultScroll = () => { const n = new Date(); return Math.max(0, (Math.min(n.getHours(), 17) - 1) * HH); };
const tab = (p, ic, label) => `<a data-act="nav" data-p="${p}" class="${S.page === p ? 'on' : ''}">${ICON[ic]}<span>${label}</span></a>`;

function sidebar() {
  const u = S.user;
  return `<aside class="sidebar glass">
    <div class="brand"><div class="logo">${ICON.cam}</div><span>${E(S.appName)}</span></div>
    <button class="btn primary block" data-act="new">${ICON.plus.replace('<svg', '<svg width="18" height="18"')} Thêm lịch chụp</button>
    ${miniCal()}
    <nav class="nav">
      <a data-act="nav" data-p="calendar" class="${S.page === 'calendar' ? 'on' : ''}">${ICON.cal}Lịch chụp</a>
      <a data-act="nav" data-p="staff" class="${S.page === 'staff' ? 'on' : ''}">${ICON.users}Nhân sự</a>
      <a data-act="nav" data-p="settings" class="${S.page === 'settings' ? 'on' : ''}">${ICON.gear}Tài khoản</a>
      ${u.is_admin ? `<a data-act="nav" data-p="admin" class="${S.page === 'admin' ? 'on' : ''}">${ICON.shield}Quản trị</a>` : ''}
    </nav>
    <div class="spacer"></div>
    <div class="userchip"><div class="avatar">${E((u.name || '?').trim().charAt(0).toUpperCase())}</div>
      <div class="t"><b>${E(u.studio_name || u.name)}</b><small>${E(u.email)}</small></div>
      <button class="iconbtn" data-act="logout" title="Đăng xuất">${ICON.out}</button></div>
  </aside>`;
}

function miniCal() {
  const m = S.mini, f = new Date(m.getFullYear(), m.getMonth(), 1), s = mondayOf(f), today = ymd(new Date()), sel = ymd(S.cur);
  let g = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'].map(x => `<div class="w">${x}</div>`).join('');
  for (let i = 0; i < 42; i++) {
    const d = addDays(s, i), k = ymd(d);
    g += `<button class="d ${d.getMonth() !== m.getMonth() ? 'o' : ''} ${k === today ? 'today' : k === sel ? 'sel' : ''}" data-act="pickday" data-date="${k}">${d.getDate()}</button>`;
  }
  return `<div class="mini"><div class="mh"><button data-act="minimove" data-n="-1">${ICON.left.replace('<svg', '<svg width="14" height="14"')}</button><span>Tháng ${m.getMonth() + 1}, ${m.getFullYear()}</span><button data-act="minimove" data-n="1">${ICON.right.replace('<svg', '<svg width="14" height="14"')}</button></div><div class="g">${g}</div></div>`;
}

function titleOf() {
  const c = S.cur;
  if (S.page !== 'calendar') return { t: { staff: 'Nhân sự', settings: 'Tài khoản', admin: 'Quản trị người dùng' }[S.page], s: '' };
  if (S.view === 'month') {
    const l = Lunar.solarToLunar(15, c.getMonth() + 1, c.getFullYear());
    return { t: `Tháng ${c.getMonth() + 1}, ${c.getFullYear()}`, s: `Âm lịch: năm ${Lunar.yearName(l.y)}` };
  }
  if (S.view === 'week') {
    const a = isMobile() ? sod(c) : mondayOf(c), b = addDays(a, isMobile() ? 2 : 6);
    return { t: `${a.getDate()}/${a.getMonth() + 1} – ${b.getDate()}/${b.getMonth() + 1}/${b.getFullYear()}`, s: `Âm lịch: ${Lunar.label(a)} → ${Lunar.label(b)}` };
  }
  if (S.view === 'day' && isMobile()) {
    const l = Lunar.solarToLunar(c.getDate(), c.getMonth() + 1, c.getFullYear());
    return { t: `${c.getDate()} thg ${c.getMonth() + 1}`, s: `${WDF[c.getDay()]} · Âm ${l.d}/${l.m}${l.leap ? 'n' : ''}` };
  }
  if (S.view === 'day') return { t: `${WDF[c.getDay()]}, ${c.getDate()}/${c.getMonth() + 1}/${c.getFullYear()}`, s: Lunar.full(c) };
  return { t: 'Lịch trình sắp tới', s: '60 ngày từ ' + c.getDate() + '/' + (c.getMonth() + 1) };
}

function topbar() {
  const { t, s } = titleOf();
  let right = '';
  if (S.page === 'calendar') {
    right = `<button class="btn small" data-act="today">Hôm nay</button>
      <button class="iconbtn" data-act="prev">${ICON.left}</button><button class="iconbtn" data-act="next">${ICON.right}</button>
      <div class="seg">${[['month', 'Tháng'], ['week', isMobile() ? '3 ngày' : 'Tuần'], ['day', 'Ngày'], ['agenda', isMobile() ? 'Danh sách' : 'Lịch trình']].map(([k, l]) => `<button data-act="view" data-v="${k}" class="${S.view === k ? 'on' : ''}">${l}</button>`).join('')}</div>`;
  }
  if (S.page === 'calendar' && isMobile()) {
    const views = [['month', 'Tháng'], ['week', '3 ngày'], ['day', 'Ngày'], ['agenda', 'Danh sách']];
    let strip = '';
    if (S.view === 'month') {
      const y0 = S.cur.getFullYear(), m0 = S.cur.getMonth();
      for (let i = -4; i <= 8; i++) {
        const d = new Date(y0, m0 + i, 1);
        if (d.getMonth() === 0) strip += `<span class="yr">${d.getFullYear()}</span>`;
        strip += `<button class="mp ${i === 0 ? 'on' : ''}" data-act="setmonth" data-y="${d.getFullYear()}" data-m="${d.getMonth()}">thg ${d.getMonth() + 1}</button>`;
      }
    }
    const nav = S.view === 'month' ? '' : `<button class="iconbtn" data-act="prev">${ICON.left}</button><button class="iconbtn" data-act="next">${ICON.right}</button>`;
    return `<div class="topbar m"><div class="tb-row"><h2>${E(t)}${s ? `<small>${E(s)}</small>` : ''}</h2>${nav}
      <button class="iconbtn today" data-act="today" title="Hôm nay"><b>${new Date().getDate()}</b></button>
      <select class="viewsel" data-sel="view">${views.map(([k, l]) => `<option value="${k}" ${S.view === k ? 'selected' : ''}>${l}</option>`).join('')}</select></div>
      ${strip ? `<div class="mstrip">${strip}</div>` : ''}${S.user.expired ? '<div class="err" style="margin:6px 0 0">Tài khoản đã hết hạn – chỉ xem được.</div>' : ''}</div>`;
  }
  const exp = S.user.expired ? `<div class="err" style="flex:1 0 100%;margin:0">Tài khoản đã hết hạn (${E(S.user.plan_expires_at)}). Bạn vẫn xem được dữ liệu nhưng chưa thể thêm/sửa lịch – hãy liên hệ để gia hạn.</div>` : '';
  return `<div class="topbar"><h2>${E(t)}${s ? `<small>${E(s)}</small>` : ''}</h2>${right}${exp}</div>`;
}

function pageHTML() {
  if (S.page === 'staff') return staffPage();
  if (S.page === 'settings') return settingsPage();
  if (S.page === 'admin') return adminPage();
  if (S.view === 'month') return monthHTML();
  if (S.view === 'week') return gridHTML(Array.from({ length: isMobile() ? 3 : 7 }, (_, i) => addDays(isMobile() ? sod(S.cur) : mondayOf(S.cur), i)));
  if (S.view === 'day') return gridHTML([sod(S.cur)]);
  return agendaHTML();
}

/* ============ Xem lịch: Tháng ============ */
function byDay() {
  const m = {};
  S.shoots.forEach(s => { (m[s.start_at.slice(0, 10)] ||= []).push(s); });
  Object.values(m).forEach(a => a.sort((x, y) => x.start_at.localeCompare(y.start_at)));
  return m;
}
const chip = s => `<div class="chip st-${shootStatus(s)}" data-act="open" data-id="${s.id}" title="${E(s.customer_name)}"><b>${s.start_at.slice(11, 16)}</b>${E(s.customer_name || s.title)}</div>`;

function monthHTML() {
  const c = S.cur, s = mondayOf(new Date(c.getFullYear(), c.getMonth(), 1)), days = byDay(), today = ymd(new Date());
  const mob = isMobile();
  let cells = '';
  for (let i = 0; i < 42; i++) {
    const d = addDays(s, i), k = ymd(d), ev = days[k] || [], l = Lunar.solarToLunar(d.getDate(), d.getMonth() + 1, d.getFullYear()), h = Lunar.holiday(d);
    cells +=`<div class="cell ${d.getMonth() !== c.getMonth() ? 'off' : ''} ${k === today ? 'today' : ''} ${d.getDay() === 0 ? 'sun' : ''}" data-act="day" data-date="${k}">
      <div class="dh"><span class="dn">${d.getDate()}</span><span class="lunar ${l.d === 1 ? 'first' : ''}">${Lunar.label(d)}</span></div>
      ${h ? `<div class="hol">${E(h)}</div>` : ''}
      ${(() => { const max = mob ? (ev.length > 3 ? 2 : 3) : 3; return ev.slice(0, max).map(chip).join('') + (ev.length > max ? `<div class="more">+${ev.length - max} nữa</div>` : ''); })()}</div>`;
  }
  return `<div class="mwrap"><div class="mhead">${['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'].map(x => `<div>${x}</div>`).join('')}</div><div class="month" style="flex:1">${cells}</div></div>`;
}
/* ============ Xem lịch: Tuần / Ngày ============ */
const HH = 56;
function layout(evs) {
  evs.sort((a, b) => a.s - b.s || b.e - a.e);
  const out = []; let cluster = [], end = 0;
  const flush = () => {
    const lanes = [];
    cluster.forEach(e => { let l = lanes.findIndex(x => x <= e.s); if (l < 0) { l = lanes.length; lanes.push(0); } lanes[l] = e.e; e.lane = l; });
    cluster.forEach(e => e.lanes = lanes.length); out.push(...cluster); cluster = [];
  };
  evs.forEach(e => { if (cluster.length && e.s >= end) { flush(); end = 0; } cluster.push(e); end = Math.max(end, e.e); });
  flush(); return out;
}
function gridHTML(days) {
  const n = days.length, cols = `${isMobile() ? 42 : 56}px repeat(${n},1fr)`, today = ymd(new Date()), all = byDay();
  const head = days.map(d => {
    const l = Lunar.solarToLunar(d.getDate(), d.getMonth() + 1, d.getFullYear()), h = Lunar.holiday(d);
    return `<div class="hc ${ymd(d) === today ? 'today' : ''}" data-act="gotoday" data-date="${ymd(d)}"><div class="wd">${WD[d.getDay()]}</div><div class="dn">${d.getDate()}</div><div class="lunar ${l.d === 1 ? 'first' : ''}">${Lunar.label(d)}${h && n === 1 ? ' · ' + E(h) : ''}</div></div>`;
  }).join('');
  let gut = ''; for (let h = 1; h < 24; h++) gut += `<span style="top:${h * HH}px">${pad(h)}:00</span>`;
  let lines = ''; for (let h = 0; h < 24; h++) lines += `<div class="hl" style="top:${h * HH}px"></div><div class="hl half" style="top:${h * HH + HH / 2}px"></div>`;
  const colsHTML = days.map(d => {
    const k = ymd(d);
    const evs = layout((all[k] || []).map(s => { const a = parseDT(s.start_at), b = parseDT(s.end_at); return { sh: s, s: a.getHours() * 60 + a.getMinutes(), e: Math.max(a.getHours() * 60 + a.getMinutes() + 30, sod(b) > sod(a) ? 1440 : b.getHours() * 60 + b.getMinutes()) }; }));
    const html = evs.map(x => {
      const top = x.s * HH / 60, h = Math.max((x.e - x.s) * HH / 60, 24);
      return `<div class="ev st-${shootStatus(x.sh)}" data-act="open" data-id="${x.sh.id}" style="top:${top}px;height:${h}px;left:calc(${x.lane / x.lanes * 100}% + 2px);width:calc(${100 / x.lanes}% - 4px)"><b>${E(x.sh.customer_name || x.sh.title)}</b><small>${x.sh.start_at.slice(11, 16)}–${x.sh.end_at.slice(11, 16)}${x.sh.location ? ' · ' + E(x.sh.location) : ''}</small></div>`;
    }).join('');
    const now = new Date(), nl = k === ymd(now) ? `<div class="nowline" style="top:${(now.getHours() * 60 + now.getMinutes()) * HH / 60}px"></div>` : '';
    return `<div class="tg-col" data-act="slot" data-date="${k}">${lines}${html}${nl}</div>`;
  }).join('');
  return `<div class="tg"><div class="tg-head" style="grid-template-columns:${cols}"><div></div>${head}</div>
    <div class="tg-body"><div class="tg-inner" style="grid-template-columns:${cols};height:${24 * HH}px"><div class="tg-gutter">${gut}</div>${colsHTML}</div></div></div>`;
}

/* ============ Xem lịch: Lịch trình ============ */
function agendaHTML() {
  const all = byDay(), keys = Object.keys(all).sort();
  if (!keys.length) return `<div class="empty">Chưa có lịch chụp nào trong 60 ngày tới.<br><br><button class="btn primary" data-act="new">Thêm lịch chụp</button></div>`;
  return `<div class="ag">${keys.map(k => {
    const d = parseDT(k + ' 00:00'), h = Lunar.holiday(d);
    return `<h4>${WDF[d.getDay()]}, ${d.getDate()}/${d.getMonth() + 1}/${d.getFullYear()} <span>· Âm ${Lunar.label(d)}${Lunar.solarToLunar(d.getDate(), d.getMonth() + 1, d.getFullYear()).d === 1 ? '' : '/' + Lunar.solarToLunar(d.getDate(), d.getMonth() + 1, d.getFullYear()).m}</span>${h ? `<em>${E(h)}</em>` : ''}</h4>` +
      all[k].map(s => `<div class="ag-item st-${shootStatus(s)}" data-act="open" data-id="${s.id}"><div class="tm">${s.start_at.slice(11, 16)}<small>${s.end_at.slice(11, 16)}</small></div><div class="bd"><b>${E(s.customer_name)}</b><span>${E(s.location || 'Chưa có địa điểm')} · ${s.staff.length} nhân sự · ${statusText[shootStatus(s)]}</span></div></div>`).join('');
  }).join('')}</div>`;
}

/* ============ Modal ============ */
function openModal(html, { wide, onMount } = {}) {
  const root = $('#modal-root');
  root.innerHTML = `<div class="scrim" data-act="scrim"><div class="sheet ${wide ? 'wide' : ''}">${html}</div></div>`;
  if (onMount) onMount(root);
}
function closeModal() { $('#modal-root').innerHTML = ''; }
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

/* ============ Chi tiết ca chụp ============ */
function openDetail(id) {
  const s = S.shoots.find(x => x.id == id); if (!s) return;
  const a = parseDT(s.start_at), b = parseDT(s.end_at), st = shootStatus(s);
  const order = ['photographer', 'assistant', 'makeup'];
  const team = [...s.staff].sort((x, y) => order.indexOf(x.role) - order.indexOf(y.role));
  openModal(`<div class="sh-head"><h3>${E(s.title || s.customer_name)}</h3><button class="iconbtn" data-act="close">✕</button></div>
  <div class="sh-body">
    <span class="pill ${st === 'ok' ? 'accepted' : st === 'none' ? 'pending' : st}">${statusText[st]}</span>
    <dl class="kv">
      <dt>Ngày</dt><dd>${WDF[a.getDay()]}, ${a.getDate()}/${a.getMonth() + 1}/${a.getFullYear()}<br><span style="color:var(--orange);font-size:13px">${E(Lunar.full(a))}</span></dd>
      <dt>Giờ</dt><dd>${hm(a)} – ${hm(b)}</dd>
      <dt>Khách hàng</dt><dd>${E(s.customer_name)}${s.customer_phone ? ` · <a href="tel:${E(s.customer_phone)}">${E(s.customer_phone)}</a>` : ''}</dd>
      ${s.location ? `<dt>Địa điểm</dt><dd><a target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(s.location)}">${E(s.location)}</a></dd>` : ''}
      ${s.notes ? `<dt>Ghi chú</dt><dd>${E(s.notes).replace(/\n/g, '<br>')}</dd>` : ''}
    </dl>
    <div class="lbl" style="margin-bottom:6px">Đội ngũ</div>
    <div class="rolelist">${team.length ? team.map(t => `<div class="roleitem"><span class="tag r-${t.role}">${ROLES[t.role]}</span>
      <div class="n"><b>${E(t.name)}</b><small>${E(t.email)}${t.phone ? ' · ' + E(t.phone) : ''}</small></div>
      <div class="acts"><span class="pill ${t.status}">${STATUS[t.status]}</span>
      ${t.status !== 'accepted' ? `<button class="btn small" data-act="setst" data-id="${t.ss_id}" data-st="accepted" title="Đánh dấu đã đồng ý (xác nhận qua điện thoại)">✓</button>` : ''}
      ${t.status !== 'declined' ? `<button class="btn small" data-act="setst" data-id="${t.ss_id}" data-st="declined" title="Đánh dấu từ chối">✕</button>` : ''}
      <button class="btn small" data-act="resend" data-id="${t.ss_id}">Gửi lại mail</button></div></div>`).join('') : '<div class="roleitem"><span style="color:var(--ink3)">Chưa gán nhân sự</span></div>'}</div>
    <div class="sh-foot" style="justify-content:space-between"><button class="btn danger" data-act="del" data-id="${s.id}">Xóa</button><button class="btn primary" data-act="edit" data-id="${s.id}">Chỉnh sửa</button></div>
  </div>`);
}

/* ============ Form thêm/sửa ca chụp ============ */
let FORM = null;
function openShootForm(shoot, presetDate, draft) {
  const st0 = draft ? parseDT(draft.date + ' ' + (draft.start || '09:00')) : shoot ? parseDT(shoot.start_at) : presetDate;
  const en0 = draft ? parseDT(draft.date + ' ' + (draft.end || '11:00')) : shoot ? parseDT(shoot.end_at) : new Date(st0.getTime() + 2 * 3600e3);
  const sel = { photographer: new Set(), assistant: new Set(), makeup: new Set() };
  if (shoot) shoot.staff.forEach(a => sel[a.role].add(+a.staff_id));
  FORM = draft || {
    id: shoot ? shoot.id : null, title: shoot ? shoot.title : '', customer_name: shoot ? shoot.customer_name : '', customer_phone: shoot ? shoot.customer_phone : '',
    location: shoot ? shoot.location : '', notes: shoot ? shoot.notes || '' : '', date: ymd(st0), start: hm(st0), end: hm(en0), sel,
    orig: shoot ? shoot.staff.map(a => a.staff_id + '|' + a.role) : [],
  };
  const F = FORM;
  const group = role => {
    const list = S.staff.filter(x => x.roles.split(',').includes(role) || F.sel[role].has(+x.id));
    return `<div class="group"><div class="gt"><span class="dot" style="background:${ROLE_COLOR[role]}"></span>${ROLES[role]}</div>
      <div class="chips">${list.map(x => `<button type="button" class="pick ${F.sel[role].has(+x.id) ? 'on' : ''}" data-act="pick" data-role="${role}" data-id="${x.id}">${E(x.name)}</button>`).join('') || `<span class="hint">Chưa có ${ROLES[role].toLowerCase()} nào được lưu.</span>`}</div></div>`;
  };
  openModal(`<div class="sh-head"><h3>${F.id ? 'Sửa lịch chụp' : 'Thêm lịch chụp'}</h3><button class="iconbtn" data-act="close">✕</button></div>
  <form class="sh-body" data-form="shoot" autocomplete="off">
    <div id="ferr"></div>
    <div class="grid2"><div class="fld"><label>Tên khách hàng *</label><input class="inp" id="f-cust" value="${E(F.customer_name)}" placeholder="VD: Anh Nam & Chị Lan" required></div>
      <div class="fld"><label>SĐT khách</label><input class="inp" id="f-phone" type="tel" value="${E(F.customer_phone)}" placeholder="09xx xxx xxx"></div></div>
    <div class="fld"><label>Tiêu đề (tuỳ chọn)</label><input class="inp" id="f-title" value="${E(F.title)}" placeholder="VD: Chụp cưới ngoại cảnh"></div>
    <div class="grid3"><div class="fld"><label>Ngày *</label><input class="inp" id="f-date" type="date" value="${F.date}" required></div>
      <div class="fld"><label>Bắt đầu *</label><input class="inp" id="f-start" type="time" step="300" value="${F.start}" required></div>
      <div class="fld"><label>Kết thúc *</label><input class="inp" id="f-end" type="time" step="300" value="${F.end}" required></div></div>
    <div class="lunarline" id="f-lunar"></div>
    <div class="fld"><label>Địa điểm</label><input class="inp" id="f-loc" value="${E(F.location)}" placeholder="Địa chỉ / tên studio / bối cảnh"></div>
    ${S.staff.length ? '' : '<div class="info">Bạn chưa lưu nhân sự nào. Thêm thợ chụp, thợ phụ, makeup để hệ thống gửi email mời tự động.</div>'}
    ${group('photographer')}${group('assistant')}${group('makeup')}
    <button type="button" class="btn small" data-act="staffnew" style="margin-bottom:12px">+ Thêm nhân sự mới</button>
    <div id="conf"></div>
    <div class="fld"><label>Ghi chú</label><textarea class="inp" id="f-notes" placeholder="Trang phục, concept, yêu cầu của khách…">${E(F.notes)}</textarea></div>
    <div class="info" id="sendnote"></div>
    <div class="sh-foot"><button type="button" class="btn" data-act="close">Hủy</button><button class="btn primary" id="f-save">${F.id ? 'Lưu thay đổi' : 'Lưu & gửi lời mời'}</button></div>
  </form>`, {
    wide: true,
    onMount: () => {
      const upd = () => { $('#f-lunar').textContent = $('#f-date').value ? '🌙 ' + Lunar.full(new Date($('#f-date').value + 'T00:00')) : ''; scheduleConflicts(); };
      let dur = (en0 - st0) / 60000;
      $('#f-start').addEventListener('change', e => { const [h, m] = e.target.value.split(':').map(Number); if (isNaN(h)) return; const t = h * 60 + m + dur; $('#f-end').value = `${pad(Math.floor(t / 60) % 24)}:${pad(t % 60)}`; upd(); });
      $('#f-end').addEventListener('change', () => { const [a, b] = $('#f-start').value.split(':').map(Number), [c, d] = $('#f-end').value.split(':').map(Number); if (!isNaN(a + c)) dur = (c * 60 + d) - (a * 60 + b); upd(); });
      $('#f-date').addEventListener('change', upd);
      upd(); updateSendNote();
    },
  });
}
function collectForm() {
  Object.assign(FORM, { customer_name: $('#f-cust').value, customer_phone: $('#f-phone').value, title: $('#f-title').value, date: $('#f-date').value, start: $('#f-start').value, end: $('#f-end').value, location: $('#f-loc').value, notes: $('#f-notes').value });
  return FORM;
}
function updateSendNote() {
  const el = $('#sendnote'); if (!el) return;
  const keys = []; Object.entries(FORM.sel).forEach(([r, set]) => set.forEach(id => keys.push(id + '|' + r)));
  if (!FORM.id) el.innerHTML = keys.length ? `📧 Khi lưu, hệ thống sẽ gửi email mời <b>${keys.length}</b> người để họ chọn <b>Đồng ý / Không nhận</b>. Nhắc lịch tự động trước 1 ngày và trước 2 giờ.` : '📧 Chọn nhân sự để hệ thống tự gửi email mời. Nhắc lịch tự động trước 1 ngày và trước 2 giờ.';
  else { const add = keys.filter(k => !FORM.orig.includes(k)).length; el.innerHTML = `📧 Người mới thêm (${add}) sẽ nhận email mời. Nếu đổi ngày/giờ/địa điểm, mọi người sẽ nhận email cập nhật và cần xác nhận lại.`; }
}
let ctimer;
function scheduleConflicts() { clearTimeout(ctimer); ctimer = setTimeout(checkConflicts, 300); }
async function checkConflicts() {
  const box = $('#conf'); if (!box || !FORM) return;
  const date = $('#f-date')?.value, st = $('#f-start')?.value, en = $('#f-end')?.value;
  const ids = new Set(); Object.values(FORM.sel).forEach(s => s.forEach(i => ids.add(i)));
  if (!date || !st || !en || !ids.size) { box.innerHTML = ''; return; }
  try {
    const r = await api('shoots_list', { query: { from: date + ' 00:00', to: ymd(addDays(new Date(date + 'T00:00'), 1)) + ' 00:00' } });
    const a = parseDT(date + ' ' + st), b = parseDT(date + ' ' + en), msgs = new Set();
    r.shoots.forEach(s => { if (s.id == FORM.id) return; const sa = parseDT(s.start_at), sb = parseDT(s.end_at);
      if (sa < b && sb > a) s.staff.forEach(x => { if (ids.has(+x.staff_id) && x.status !== 'declined') msgs.add(`${x.name} đã có ca ${hm(sa)}–${hm(sb)} (${s.customer_name})`); }); });
    const cur = $('#conf'); if (cur) cur.innerHTML = msgs.size ? `<div class="warn">⚠️ Trùng lịch:<br>${[...msgs].map(E).join('<br>')}</div>` : '';
  } catch (e) { /* bỏ qua */ }
}
async function saveShoot() {
  collectForm();
  const F = FORM, btn = $('#f-save'), err = $('#ferr');
  const staff = []; Object.entries(F.sel).forEach(([role, set]) => set.forEach(id => staff.push({ staff_id: id, role })));
  btn.disabled = true; err.innerHTML = '';
  try {
    const r = await api('shoot_save', { body: { id: F.id, title: F.title, customer_name: F.customer_name, customer_phone: F.customer_phone, location: F.location, notes: F.notes, start_at: `${F.date} ${F.start}`, end_at: `${F.date} ${F.end}`, staff } });
    closeModal();
    S.cur = new Date(F.date + 'T00:00'); S.mini = new Date(S.cur);
    await refresh();
    toast(r.failed ? `Đã lưu. Gửi được ${r.sent} email, ${r.failed} email lỗi (kiểm tra cấu hình SMTP).` : r.sent ? `Đã lưu và gửi ${r.sent} email` : 'Đã lưu lịch chụp', !!r.failed);
  } catch (e) { err.innerHTML = `<div class="err">${E(e.message)}</div>`; btn.disabled = false; }
}

/* ============ Nhân sự ============ */
function staffPage() {
  const list = S.staff;
  return `<div class="page"><p class="hint" style="margin:0 0 4px">Lưu sẵn thợ chụp, thợ phụ, makeup để chọn nhanh khi tạo lịch. Hệ thống sẽ gửi email mời đến địa chỉ bên dưới.</p>
    <button class="btn primary" data-act="staffadd">+ Thêm nhân sự</button>
    ${list.length ? `<div class="cards">${list.map(x => `<div class="card"><div class="top"><div class="avatar" style="background:linear-gradient(145deg,#7cc0ff,#7c8cff)">${E(x.name.charAt(0).toUpperCase())}</div><div style="min-width:0"><h4>${E(x.name)}</h4><small>${E(x.email)}</small></div></div>
      <div class="row">${x.roles.split(',').map(r => `<span class="tag r-${r}">${ROLES[r]}</span>`).join('')}</div>
      ${x.phone ? `<small>📞 ${E(x.phone)}</small>` : ''}${x.note ? `<small>${E(x.note)}</small>` : ''}
      <div class="row" style="margin-top:4px"><button class="btn small" data-act="staffedit" data-id="${x.id}">Sửa</button><button class="btn small danger" data-act="staffdel" data-id="${x.id}">Xóa</button></div></div>`).join('')}</div>` : '<div class="empty">Chưa có nhân sự nào.</div>'}</div>`;
}
function openStaffForm(x, onDone) {
  const roles = x ? x.roles.split(',') : ['photographer'];
  openModal(`<div class="sh-head"><h3>${x ? 'Sửa nhân sự' : 'Thêm nhân sự'}</h3><button class="iconbtn" data-act="staffclose">✕</button></div>
  <form class="sh-body" data-form="staff" autocomplete="off"><div id="ferr"></div>
    <div class="fld"><label>Họ tên *</label><input class="inp" id="s-name" value="${E(x?.name)}" required></div>
    <div class="fld"><label>Email nhận lịch (Gmail) *</label><input class="inp" id="s-email" type="email" value="${E(x?.email)}" required placeholder="ten@gmail.com"></div>
    <div class="fld"><label>Số điện thoại</label><input class="inp" id="s-phone" type="tel" value="${E(x?.phone)}"></div>
    <div class="fld"><label>Vai trò (chọn nhiều được)</label><div class="chips" id="s-roles">${Object.entries(ROLES).map(([k, l]) => `<button type="button" class="pick ${roles.includes(k) ? 'on' : ''}" data-act="rolepick" data-role="${k}">${l}</button>`).join('')}</div></div>
    <div class="fld"><label>Ghi chú</label><input class="inp" id="s-note" value="${E(x?.note)}" placeholder="VD: chuyên chụp cưới, có xe riêng…"></div>
    <div class="sh-foot"><button type="button" class="btn" data-act="staffclose">Hủy</button><button class="btn primary">Lưu</button></div></form>`);
  STAFF_CTX = { id: x?.id, onDone };
}
let STAFF_CTX = null;
async function saveStaff() {
  const roles = [...document.querySelectorAll('#s-roles .pick.on')].map(b => b.dataset.role);
  try {
    await api('staff_save', { body: { id: STAFF_CTX.id, name: $('#s-name').value, email: $('#s-email').value, phone: $('#s-phone').value, note: $('#s-note').value, roles } });
    await loadStaff(); const cb = STAFF_CTX.onDone; closeModal(); toast('Đã lưu nhân sự');
    if (cb) cb(); else render();
  } catch (e) { $('#ferr').innerHTML = `<div class="err">${E(e.message)}</div>`; }
}

/* ============ Tài khoản / Quản trị ============ */
function settingsPage() {
  const u = S.user;
  return `<div class="page">
    <div class="sect"><h3>Thông tin</h3><form data-form="profile">
      <div class="grid2"><div class="fld"><label>Tên của bạn</label><input class="inp" name="name" value="${E(u.name)}" required></div><div class="fld"><label>Tên studio / thương hiệu</label><input class="inp" name="studio_name" value="${E(u.studio_name)}"></div></div>
      <div class="grid2"><div class="fld"><label>Email đăng nhập (nhận nhắc lịch)</label><input class="inp" value="${E(u.email)}" disabled></div><div class="fld"><label>Số điện thoại</label><input class="inp" name="phone" value="${E(u.phone)}"></div></div>
      <div class="switch"><div><b>Nhắc trước 1 ngày</b><br><small style="color:var(--ink3)">Gửi email cho mọi người trong ca trước 24 giờ</small></div><input type="checkbox" class="tgl" name="notify_24h" ${u.notify_24h ? 'checked' : ''}></div>
      <div class="switch"><div><b>Nhắc trước 2 tiếng</b><br><small style="color:var(--ink3)">Gửi email cho mọi người trong ca trước 2 giờ</small></div><input type="checkbox" class="tgl" name="notify_2h" ${u.notify_2h ? 'checked' : ''}></div>
      <div style="margin-top:10px"><button class="btn primary">Lưu thay đổi</button></div></form></div>
    <div class="sect"><h3>Gói sử dụng</h3><p style="margin:0">${u.is_admin ? 'Quản trị viên – không giới hạn.' : u.plan_expires_at ? `Hạn sử dụng đến <b>${E(u.plan_expires_at.split('-').reverse().join('/'))}</b>${u.expired ? ' <span class="pill declined">Đã hết hạn</span>' : ''}` : 'Không giới hạn thời gian.'}</p></div>
    <div class="sect"><h3>Đổi mật khẩu</h3><form data-form="password"><div class="grid2"><div class="fld"><label>Mật khẩu hiện tại</label><input class="inp" type="password" name="old" required autocomplete="current-password"></div><div class="fld"><label>Mật khẩu mới (≥ 8 ký tự)</label><input class="inp" type="password" name="new" required minlength="8" autocomplete="new-password"></div></div><button class="btn">Đổi mật khẩu</button></form></div>
    <button class="btn danger" data-act="logout">Đăng xuất</button></div>`;
}
function adminPage() {
  return `<div class="page"><div class="sect"><h3>Người dùng (${S.adminUsers.length})</h3><div style="overflow:auto"><table class="tbl"><thead><tr><th>Tài khoản</th><th>Số ca</th><th>Hết hạn</th><th>Hoạt động</th><th></th></tr></thead><tbody>
    ${S.adminUsers.map(x => `<tr data-uid="${x.id}"><td><b>${E(x.name)}</b>${x.is_admin == 1 ? ' <span class="pill accepted">Admin</span>' : ''}<br><small style="color:var(--ink3)">${E(x.email)}${x.studio_name ? ' · ' + E(x.studio_name) : ''}</small></td><td>${x.shoots}</td>
    <td>${x.is_admin == 1 ? '—' : `<input class="inp" type="date" value="${E(x.plan_expires_at || '')}" data-f="exp">`}</td>
    <td>${x.is_admin == 1 ? '—' : `<input type="checkbox" class="tgl" data-f="act" ${x.is_active == 1 ? 'checked' : ''}>`}</td>
    <td>${x.is_admin == 1 ? '' : '<button class="btn small" data-act="adminsave">Lưu</button>'}</td></tr>`).join('')}</tbody></table></div>
    <p class="hint" style="margin-top:12px">Để trống ô “Hết hạn” = dùng không giới hạn. Khi hết hạn, người dùng vẫn xem được dữ liệu nhưng không thêm/sửa lịch được.</p></div></div>`;
}

/* ============ Đăng nhập / Đăng ký ============ */
function renderAuth() {
  const reg = S.authMode === 'register';
  app.innerHTML = `<div class="auth"><div class="box glass"><div class="logo">${ICON.cam}</div>
    <h1>${reg ? 'Tạo tài khoản' : 'Chào mừng trở lại'}</h1><p class="sub">${reg ? 'Quản lý lịch chụp cho studio của bạn' : 'Đăng nhập để xem lịch chụp'}</p>
    <form data-form="${reg ? 'register' : 'login'}"><div id="ferr"></div>
      ${reg ? `<div class="fld"><label>Tên của bạn</label><input class="inp" name="name" required></div><div class="fld"><label>Tên studio (tuỳ chọn)</label><input class="inp" name="studio_name"></div>` : ''}
      <div class="fld"><label>Email</label><input class="inp" type="email" name="email" required autocomplete="email"></div>
      <div class="fld"><label>Mật khẩu</label><input class="inp" type="password" name="password" required minlength="${reg ? 8 : 1}" autocomplete="${reg ? 'new-password' : 'current-password'}"></div>
      <button class="btn primary block" style="padding:14px;margin-top:4px">${reg ? 'Đăng ký' : 'Đăng nhập'}</button></form>
    ${reg || S.canRegister ? `<div class="switchlink">${reg ? 'Đã có tài khoản? <a data-act="authmode" data-m="login">Đăng nhập</a>' : 'Chưa có tài khoản? <a data-act="authmode" data-m="register">Đăng ký</a>'}</div>` : ''}
  </div></div>`;
}

/* ============ Điều hướng ============ */
function shift(n) {
  const c = S.cur;
  if (S.view === 'month') {
    S.cur = new Date(c.getFullYear(), c.getMonth() + n, 1);
    const t = new Date();
    if (isMobile() && S.cur.getMonth() === t.getMonth() && S.cur.getFullYear() === t.getFullYear()) S.cur = sod(t);
  }
  else if (S.view === 'week') S.cur = addDays(c, (isMobile() ? 3 : 7) * n);
  else if (S.view === 'day') S.cur = addDays(c, n);
  else S.cur = addDays(c, 30 * n);
  S.mini = new Date(S.cur);
}
async function goto() { S.tgScroll = S.tgScroll; await refresh(); }
function newAt(d) { openShootForm(null, d); }

/* ============ Sự kiện ============ */
const H = {
  scrim: (t, ev) => { if (ev.target === t) closeModal(); },
  close: () => closeModal(),
  staffclose: () => { const cb = STAFF_CTX?.onDone; closeModal(); if (cb) cb(); },
  nav: async t => { S.page = t.dataset.p; if (S.page === 'admin') { try { S.adminUsers = (await api('admin_users')).users; } catch (e) { toast(e.message, true); } } render(); },
  authmode: t => { S.authMode = t.dataset.m; renderAuth(); },
  logout: async () => { await api('logout', { body: {} }); S.user = null; S.shoots = []; S.staff = []; S.page = 'calendar'; render(); },
  today: () => { S.cur = new Date(); S.mini = new Date(); S.tgScroll = null; goto(); },
  prev: () => { shift(-1); goto(); },
  next: () => { shift(1); goto(); },
  view: t => { S.view = t.dataset.v; S.tgScroll = null; goto(); },
  minimove: t => { S.mini = new Date(S.mini.getFullYear(), S.mini.getMonth() + +t.dataset.n, 1); render(); },
  pickday: t => { S.cur = parseDT(t.dataset.date + ' 00:00'); S.mini = new Date(S.cur); S.page = 'calendar'; goto(); },
  gotoday: t => { S.cur = parseDT(t.dataset.date + ' 00:00'); S.mini = new Date(S.cur); S.view = 'day'; S.tgScroll = null; goto(); },
  day: t => {
    const d = parseDT(t.dataset.date + ' 09:00');
    if (!isMobile()) return newAt(d);
    S.cur = sod(d); S.mini = new Date(S.cur); S.view = 'day'; S.tgScroll = null; goto();
  },
  setmonth: t => { S.cur = new Date(+t.dataset.y, +t.dataset.m, 1); S.mini = new Date(S.cur); goto(); },
  slot: (t, ev) => { const y = ev.clientY - t.getBoundingClientRect().top, mins = Math.floor(y / HH * 2) * 30; const d = parseDT(t.dataset.date + ' 00:00'); d.setMinutes(Math.min(mins, 22 * 60)); newAt(d); },
  new: () => {
    if (S.user.expired) return toast('Tài khoản đã hết hạn', true);
    const d = sod(S.cur);
    if (d.getTime() === sod(new Date()).getTime()) d.setHours(Math.min(new Date().getHours() + 1, 20)); else d.setHours(9);
    newAt(d);
  },
  open: t => openDetail(t.dataset.id),
  edit: t => { const s = S.shoots.find(x => x.id == t.dataset.id); if (s) openShootForm(s); },
  del: async t => {
    if (!confirm('Xóa ca chụp này? Những người đã được mời (chưa từ chối) sẽ nhận email báo hủy.')) return;
    try { await api('shoot_delete', { body: { id: +t.dataset.id } }); closeModal(); await refresh(); toast('Đã xóa'); } catch (e) { toast(e.message, true); }
  },
  resend: async t => { try { const r = await api('assignment_resend', { body: { id: +t.dataset.id } }); toast(r.sent ? 'Đã gửi lại email' : 'Gửi email thất bại – kiểm tra SMTP', !r.sent); } catch (e) { toast(e.message, true); } },
  setst: async t => { try { await api('assignment_status', { body: { id: +t.dataset.id, status: t.dataset.st } }); await loadShoots(); const id = S.shoots.find(s => s.staff.some(x => x.ss_id == t.dataset.id))?.id; render(); if (id) openDetail(id); } catch (e) { toast(e.message, true); } },
  pick: t => {
    const set = FORM.sel[t.dataset.role], id = +t.dataset.id;
    set.has(id) ? set.delete(id) : set.add(id); t.classList.toggle('on'); updateSendNote(); scheduleConflicts();
  },
  rolepick: t => t.classList.toggle('on'),
  staffnew: () => { const d = collectForm(); openStaffForm(null, () => openShootForm(null, null, d)); },
  staffadd: () => openStaffForm(null),
  staffedit: t => openStaffForm(S.staff.find(x => x.id == t.dataset.id)),
  staffdel: async t => { if (!confirm('Xóa nhân sự này? Họ cũng sẽ bị gỡ khỏi các ca chụp đã gán.')) return; try { await api('staff_delete', { body: { id: +t.dataset.id } }); await refresh(); toast('Đã xóa'); } catch (e) { toast(e.message, true); } },
  adminsave: async t => {
    const tr = t.closest('tr');
    try { await api('admin_user_update', { body: { id: +tr.dataset.uid, plan_expires_at: $('[data-f=exp]', tr).value, is_active: $('[data-f=act]', tr).checked } }); toast('Đã cập nhật'); } catch (e) { toast(e.message, true); }
  },
};
document.addEventListener('click', ev => {
  const t = ev.target.closest('[data-act]'); if (!t) return;
  const f = H[t.dataset.act]; if (f) { if (t.tagName === 'A') ev.preventDefault(); f(t, ev); }
});

document.addEventListener('change', ev => {
  if (ev.target.dataset?.sel === 'view') { S.view = ev.target.value; S.tgScroll = null; goto(); }
});

document.addEventListener('submit', async ev => {
  const f = ev.target.closest('form[data-form]'); if (!f) return;
  ev.preventDefault();
  const kind = f.dataset.form, fd = Object.fromEntries(new FormData(f).entries());
  const err = $('#ferr', f.closest('.box, .sheet') || document);
  const fail = e => { if (err) err.innerHTML = `<div class="err">${E(e.message)}</div>`; else toast(e.message, true); };
  try {
    if (kind === 'login' || kind === 'register') {
      const r = await api(kind, { body: fd }); S.user = r.user; S.page = 'calendar'; await refresh();
    } else if (kind === 'shoot') await saveShoot();
    else if (kind === 'staff') await saveStaff();
    else if (kind === 'profile') {
      const r = await api('profile_save', { body: { ...fd, notify_24h: !!fd.notify_24h, notify_2h: !!fd.notify_2h } }); S.user = r.user; render(); toast('Đã lưu');
    } else if (kind === 'password') { await api('password_change', { body: fd }); f.reset(); toast('Đã đổi mật khẩu'); }
  } catch (e) { fail(e); }
});

window.addEventListener('resize', (() => { let w = innerWidth <= 860, t; return () => { clearTimeout(t); t = setTimeout(() => { const m = innerWidth <= 860; if (m !== w) { w = m; if (S.user && !$('.sheet')) refresh(); } }, 150); }; })());

/* ============ Khởi động ============ */
(async () => {
  render();
  try {
    const r = await api('me');
    S.csrf = r.csrf; S.user = r.user; S.appName = r.app_name || 'Lịch Chụp'; S.canRegister = r.can_register; S.ready = true;
    if (S.appName) document.title = S.appName + ' – Quản lý lịch trình nhiếp ảnh';
    if (S.user) await refresh(); else render();
  } catch (e) { S.error = e.message; render(); }
  setInterval(() => { if (S.user && S.page === 'calendar' && !$('.sheet') && document.visibilityState === 'visible') loadShoots().then(render).catch(() => {}); }, 120000);
})();
})();
