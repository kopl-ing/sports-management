// Match screen: drag (pointer events, so touch works) or tap-then-tap to move players, tap-to-score
// goals and tab memory. Delegated on `document`, so it survives boosted body swaps.
const DRAG_THRESHOLD = 6;

let press = null;
let ghost = null;
let selected = null;
let over = null;
let goalScorer = null;
let sanctioned = null;

const editableField = (el) => el?.closest('[data-sm-field][data-sm-editable]');
const onBench = (el) => el.closest('[data-sm-bench]') !== null;
const zoneOf = (el) => el.closest('[data-sm-zone]')?.dataset.smZone ?? null;

function flag(el, name, on) {
    if (on) {
        el?.setAttribute(name, '');
    } else {
        el?.removeAttribute(name);
    }
}

function setOver(target) {
    if (over !== target) {
        flag(over, 'data-sm-over', false);
        over = target;
        flag(over, 'data-sm-over', true);
    }
}

function select(player) {
    flag(selected, 'data-sm-selected', false);
    selected = player;
    flag(selected, 'data-sm-selected', true);
}

function refuse(el) {
    flag(el, 'data-sm-refused', true);
    setTimeout(() => flag(el, 'data-sm-refused', false), 600);
}

function targetAt(x, y, field) {
    const el = document.elementFromPoint(x, y);
    if (!el || editableField(el) !== field) {
        return null;
    }

    return el.closest('[data-sm-player]') ?? el.closest('[data-sm-zone], [data-sm-bench]');
}

function exceedsLimits(field, player, zone) {
    const from = zoneOf(player);
    const onField = field.querySelectorAll('[data-sm-zone] [data-sm-player]').length;
    const keeper = field.dataset.smKeeper;
    const keepers = keeper ? field.querySelectorAll(`[data-sm-zone="${keeper}"] [data-sm-player]`).length : 0;
    const max = Number(field.dataset.smMax) || Infinity;

    return (from === null && onField + 1 > max) || (!!keeper && zone === keeper && from !== keeper && keepers >= 1);
}

function playerBefore(zone, player, x, y) {
    return [...zone.querySelectorAll('[data-sm-player]')]
        .filter((other) => other !== player)
        .find((other) => {
            const { top, bottom, left, width } = other.getBoundingClientRect();
            return y < top || (y <= bottom && x < left + width / 2);
        }) ?? null;
}

function nextPlayer(player) {
    let next = player.nextElementSibling;
    while (next && !next.matches('[data-sm-player]')) {
        next = next.nextElementSibling;
    }

    return next;
}

function swapNodes(a, b) {
    const marker = document.createComment('');
    a.replaceWith(marker);
    b.replaceWith(a);
    marker.replaceWith(b);
}

function move(player, target, x, y) {
    const field = editableField(player);
    const form = field?.querySelector('form[data-sm-move]');
    if (!form || !target || target === player) {
        return;
    }

    const entering = onBench(player) && !onBench(target) ? player : (onBench(target) && !onBench(player) && target.matches('[data-sm-player]') ? target : null);
    if (entering?.hasAttribute('data-sm-unavailable')) {
        refuse(entering);
        return;
    }

    let memberId = player.dataset.smPlayer;
    let replaceId = '';
    let beforeId = '';
    let zone = '';

    if (target.matches('[data-sm-player]')) {
        if (onBench(target) && onBench(player)) {
            return;
        }
        replaceId = target.dataset.smPlayer;
        if (onBench(target)) {
            [memberId, replaceId] = [replaceId, memberId];
        }
        swapNodes(player, target);
        flag(target, 'data-sm-pending', true);
    } else if (target.matches('[data-sm-zone]')) {
        zone = target.dataset.smZone;
        const before = x === undefined ? null : playerBefore(target, player, x, y);
        if (zone === zoneOf(player) && before === nextPlayer(player)) {
            return;
        }
        if (exceedsLimits(field, player, zone)) {
            refuse(target);
            return;
        }
        target.insertBefore(player, before);
        beforeId = before?.dataset.smPlayer ?? '';
    } else {
        if (onBench(player)) {
            return;
        }
        target.querySelector('[data-sm-bench-list]')?.append(player);
    }

    flag(player, 'data-sm-pending', true);
    form.elements.team_member_id.value = memberId;
    form.elements.zone.value = zone;
    form.elements.replace_team_member_id.value = replaceId;
    form.elements.before_team_member_id.value = beforeId;
    form.requestSubmit();
}

function endGoal(field) {
    goalScorer = null;
    select(null);
    delete field?.dataset.smGoal;
}

function submitGoal(field, scorerId, assistId, ownGoal = false) {
    const form = field.querySelector('form[data-sm-goal-form]');
    form.elements.scorer_team_member_id.value = scorerId ?? '';
    form.elements.assist_team_member_id.value = assistId ?? '';
    form.elements.own_goal.value = ownGoal ? '1' : '';
    endGoal(field);
    form.requestSubmit();
}

function tapInGoalMode(field, player) {
    if (field.dataset.smGoal === 'scorer') {
        goalScorer = player.dataset.smPlayer;
        select(player);
        field.dataset.smGoal = 'assist';
    } else if (player.dataset.smPlayer !== goalScorer) {
        submitGoal(field, goalScorer, player.dataset.smPlayer);
    }
}

function endSanction(field) {
    sanctioned = null;
    select(null);
    delete field?.dataset.smSanction;
}

function submitSanction(field, memberId, kind) {
    const form = field.querySelector('form[data-sm-sanction-form]');
    form.elements.team_member_id.value = memberId;
    form.elements.kind.value = kind;
    endSanction(field);
    form.requestSubmit();
}

function tapInSanctionMode(field, player) {
    if (field.dataset.smSanction !== 'player') {
        return;
    }
    const kinds = field.querySelectorAll('[data-sm-sanction-kind]');
    if (kinds.length === 1) {
        submitSanction(field, player.dataset.smPlayer, kinds[0].dataset.smSanctionKind);
        return;
    }
    sanctioned = player.dataset.smPlayer;
    select(player);
    field.dataset.smSanction = 'kind';
}

function endDrag() {
    ghost?.remove();
    ghost = null;
    flag(press?.player, 'data-sm-dragging', false);
    setOver(null);
}

document.addEventListener('pointerdown', (event) => {
    const player = event.target.closest('[data-sm-player]');
    if (!player || !editableField(player) || event.button !== 0) {
        return;
    }

    press = { player, x: event.clientX, y: event.clientY, pointerId: event.pointerId };
});

document.addEventListener('pointermove', (event) => {
    const pressedField = press && editableField(press.player);
    if (!press || event.pointerId !== press.pointerId || pressedField.dataset.smGoal || pressedField.dataset.smSanction) {
        return;
    }

    if (!ghost) {
        if (Math.hypot(event.clientX - press.x, event.clientY - press.y) < DRAG_THRESHOLD) {
            return;
        }
        select(null);
        ghost = press.player.cloneNode(true);
        ghost.removeAttribute('data-sm-player');
        Object.assign(ghost.style, { position: 'fixed', left: '0', top: '0', zIndex: '50', pointerEvents: 'none' });
        document.body.append(ghost);
        flag(press.player, 'data-sm-dragging', true);
    }

    const { width, height } = ghost.getBoundingClientRect();
    ghost.style.transform = `translate(${event.clientX - width / 2}px, ${event.clientY - height / 2}px)`;
    setOver(targetAt(event.clientX, event.clientY, editableField(press.player)));
});

document.addEventListener('pointerup', (event) => {
    if (!press || event.pointerId !== press.pointerId) {
        return;
    }

    const { player } = press;
    const field = editableField(player);

    if (field.dataset.smGoal) {
        tapInGoalMode(field, player);
    } else if (field.dataset.smSanction) {
        tapInSanctionMode(field, player);
    } else if (ghost) {
        const target = targetAt(event.clientX, event.clientY, field);
        endDrag();
        move(player, target, event.clientX, event.clientY);
    } else if (selected && selected !== player) {
        const from = selected;
        select(null);
        move(from, player);
    } else {
        select(selected === player ? null : player);
    }

    press = null;
});

document.addEventListener('pointercancel', () => {
    endDrag();
    press = null;
});

document.addEventListener('click', (event) => {
    const field = event.target.closest('[data-sm-field]');

    const goalStart = event.target.closest('[data-sm-goal-start]');
    if (goalStart) {
        select(null);
        field.querySelector('form[data-sm-goal-form]').elements.points.value = goalStart.dataset.smPoints ?? '1';
        field.dataset.smGoal = 'scorer';
        return;
    }
    if (event.target.closest('[data-sm-sanction-start]')) {
        select(null);
        field.dataset.smSanction = 'player';
        return;
    }
    const kind = event.target.closest('[data-sm-sanction-kind]');
    if (kind) {
        submitSanction(field, sanctioned, kind.dataset.smSanctionKind);
        return;
    }
    if (event.target.closest('[data-sm-sanction-cancel]')) {
        endSanction(field);
        return;
    }
    const comeback = event.target.closest('[data-sm-select-player]');
    if (comeback) {
        select(field.querySelector(`[data-sm-player="${comeback.dataset.smSelectPlayer}"]`));
        return;
    }
    if (event.target.closest('[data-sm-goal-own]')) {
        submitGoal(field, null, null, true);
        return;
    }
    if (event.target.closest('[data-sm-goal-skip]')) {
        submitGoal(field, goalScorer, null);
        return;
    }
    if (event.target.closest('[data-sm-goal-cancel]')) {
        endGoal(field);
        return;
    }

    if (!selected || event.target.closest('[data-sm-player]')) {
        return;
    }

    const target = event.target.closest('[data-sm-zone], [data-sm-bench]');
    const from = selected;
    select(null);

    if (target && editableField(target) === editableField(from)) {
        move(from, target, event.clientX, event.clientY);
    }
});

document.addEventListener('change', (event) => {
    if (event.target.name === 'sm-tab') {
        try {
            sessionStorage.setItem('sm-tab', event.target.value);
        } catch {}
    }
});

function syncPage() {
    const field = document.querySelector('[data-sm-field]');
    document.documentElement.style.overscrollBehaviorY = field ? 'none' : '';

    try {
        const tab = sessionStorage.getItem('sm-tab');
        const input = tab && document.querySelector(`input[name="sm-tab"][value="${tab}"]`);
        if (input) {
            input.checked = true;
        }
    } catch {}
}

document.addEventListener('htmx:after:settle', syncPage);
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', syncPage);
} else {
    syncPage();
}
