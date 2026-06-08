<script setup>
import { computed, ref, watch } from 'vue';
import { useLeagueStore } from '../stores/league';
import { t, dateLocale } from '../i18n';

const league = useLeagueStore();

const detail = computed(() => league.openGame);
const game = computed(() => detail.value?.game ?? null);

const editing = ref(false);
const editHome = ref(0);
const editAway = ref(0);

// Close the editor whenever a different match is opened.
watch(() => league.openGameId, () => { editing.value = false; });

function startEdit() {
    editHome.value = game.value.home_goals;
    editAway.value = game.value.away_goals;
    editing.value = true;
}

function clampGoals(value) {
    return Math.max(0, Math.min(99, Number(value) || 0));
}

async function saveEdit() {
    await league.updateGame(game.value.id, clampGoals(editHome.value), clampGoals(editAway.value));
    editing.value = false;
}

const hasAbsentees = computed(() => {
    if (!detail.value) return false;

    const { suspensions, injuries } = detail.value;

    return ['home', 'away'].some(
        (side) => (suspensions?.[side]?.length ?? 0) + (injuries?.[side]?.length ?? 0) > 0,
    );
});

const motm = computed(() => {
    if (!detail.value || !game.value?.is_played) return null;

    const all = [...detail.value.lineups.home, ...detail.value.lineups.away];

    return all.reduce(
        (best, row) => (row.rating !== null && (best === null || row.rating > best.rating) ? row : best),
        null,
    )?.player_id ?? null;
});

const eventIcons = {
    goal: '⚽',
    yellow: '🟨',
    red: '🟥',
    injury: '✖',
    sub: '⇄',
};

// Per-player badges for the lineup rows: goals, cards, injuries.
const playerEvents = computed(() => {
    const map = {};

    for (const event of detail.value?.events ?? []) {
        if (!event.player_id) continue;

        const entry = (map[event.player_id] ??= { goals: 0, yellow: 0, red: false, injury: false });

        if (event.type === 'goal') entry.goals++;
        if (event.type === 'yellow') entry.yellow++;
        if (event.type === 'red') entry.red = true;
        if (event.type === 'injury') entry.injury = true;
    }

    return map;
});

function kickoffLabel(value) {
    if (!value) return '';

    const date = new Date(value);

    return `${date.toLocaleDateString(dateLocale(), { weekday: 'short', day: 'numeric', month: 'short' }).toUpperCase()} · ${date.toLocaleTimeString(dateLocale(), { hour: '2-digit', minute: '2-digit' })}`;
}

function ratingTone(value) {
    if (value === null) return 'is-mid';
    if (value >= 8) return 'is-elite';
    if (value >= 7) return 'is-great';
    if (value >= 6) return 'is-good';
    return 'is-mid';
}

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, (ch) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[ch]);
}

// Localised commentary rebuilt from the engine's template key + name
// params; falls back to the stored English line for legacy events.
function renderCommentary(event) {
    if (!event.template) {
        let text = escapeHtml(event.commentary);

        for (const name of [event.player?.name, event.related_player?.name]) {
            if (name) text = text.replaceAll(escapeHtml(name), `<b>${escapeHtml(name)}</b>`);
        }

        return text;
    }

    const params = event.params ?? {};
    const bold = (value) => `<b>${escapeHtml(value)}</b>`;

    let text = t(`commentary.${event.template}`, {
        player: params.player ? bold(params.player) : '',
        in: params.in ? bold(params.in) : '',
        out: params.out ? bold(params.out) : '',
    });

    if (params.assist) {
        text += t('commentary.assist', { assist: bold(params.assist) });
    }

    if (params.matches) {
        text += t('commentary.sidelined', { n: params.matches });
    }

    if (params.et) {
        text = t('commentary.et') + text;
    }

    return text;
}

function formArrow(factor) {
    if (!factor || Math.abs(factor - 1) < 0.01) return null;

    return factor > 1 ? '▲' : '▼';
}

function formClass(factor) {
    return factor > 1 ? 'is-up' : 'is-down';
}

function banLabel(reason) {
    return reason === 'red card' ? t('match.banRed') : t('match.banYellows');
}
</script>

<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="league.openGameId" class="modal" @click.self="league.closeMatch()">
                <div class="modal__card modal__card--match" role="dialog" aria-modal="true">
                    <button type="button" class="modal__close" aria-label="Close" @click="league.closeMatch()">&#10005;</button>

                    <div v-if="league.gameLoading || !detail" class="loading loading--modal">
                        <span class="loading__ball"></span>
                        <span class="loading__text">{{ t('loading.match') }}</span>
                    </div>

                    <template v-else>
                        <p class="match__stage">
                            {{ t(`stage.${game.stage}.s`) }}<template v-if="game.leg"> · {{ t('match.leg', { n: game.leg }) }}</template>
                            · {{ kickoffLabel(game.kickoff_at) }}
                        </p>

                        <header class="match__head">
                            <div class="match__team">
                                <img v-if="game.home_team.logo_url" :src="game.home_team.logo_url" :alt="game.home_team.name" class="match__logo">
                                <b>{{ game.home_team.code }}</b>
                                <span>{{ game.home_team.name }}</span>
                            </div>
                            <div class="match__score">
                                <template v-if="editing">
                                    <input v-model.number="editHome" class="match__input" type="number" min="0" max="99" aria-label="Home goals">
                                    <i>:</i>
                                    <input v-model.number="editAway" class="match__input" type="number" min="0" max="99" aria-label="Away goals">
                                </template>
                                <template v-else-if="game.is_played">
                                    {{ game.home_goals }}<i>:</i>{{ game.away_goals }}
                                </template>
                                <span v-else class="match__vs">VS</span>
                            </div>
                            <div class="match__team match__team--away">
                                <img v-if="game.away_team.logo_url" :src="game.away_team.logo_url" :alt="game.away_team.name" class="match__logo">
                                <b>{{ game.away_team.code }}</b>
                                <span>{{ game.away_team.name }}</span>
                            </div>
                        </header>

                        <div v-if="game.is_played" class="match__edit">
                            <template v-if="editing">
                                <button
                                    type="button"
                                    class="match__edit-btn match__edit-btn--save"
                                    :disabled="league.busyAction === 'edit'"
                                    @click="saveEdit"
                                >
                                    <span v-if="league.busyAction === 'edit'" class="controls__spinner" aria-hidden="true"></span>
                                    <template v-else>&#10003; {{ t('match.editSave') }}</template>
                                </button>
                                <button type="button" class="match__edit-btn" @click="editing = false">{{ t('match.editCancel') }}</button>
                            </template>
                            <button v-else type="button" class="match__edit-btn" @click="startEdit">
                                &#9998; {{ t('match.editScore') }}
                            </button>
                        </div>

                        <section class="probs" aria-label="Win probabilities">
                            <p class="probs__label">{{ game.is_played ? t('match.preOdds') : t('match.winProb') }}</p>
                            <div class="probs__bar">
                                <span class="probs__seg probs__seg--home" :style="{ width: `${detail.probabilities.home}%` }"></span>
                                <span class="probs__seg probs__seg--draw" :style="{ width: `${detail.probabilities.draw}%` }"></span>
                                <span class="probs__seg probs__seg--away" :style="{ width: `${detail.probabilities.away}%` }"></span>
                            </div>
                            <div class="probs__legend">
                                <span>
                                    <b>{{ game.home_team.code }}</b> {{ detail.probabilities.home }}%
                                    <i v-if="formArrow(detail.form?.home)" class="probs__form" :class="formClass(detail.form.home)">{{ formArrow(detail.form.home) }}</i>
                                </span>
                                <span>{{ t('match.drawLabel') }} {{ detail.probabilities.draw }}%</span>
                                <span>
                                    <i v-if="formArrow(detail.form?.away)" class="probs__form" :class="formClass(detail.form.away)">{{ formArrow(detail.form.away) }}</i>
                                    <b>{{ game.away_team.code }}</b> {{ detail.probabilities.away }}%
                                </span>
                            </div>
                            <p v-if="formArrow(detail.form?.home) || formArrow(detail.form?.away)" class="probs__hint">
                                {{ t('match.formHint') }}
                            </p>
                        </section>

                        <section
                            v-if="hasAbsentees"
                            class="bans"
                            aria-label="Unavailable players"
                        >
                            <h3 class="match__subtitle">{{ t('match.unavailable') }}</h3>
                            <div class="bans__grid">
                                <div v-for="side in ['home', 'away']" :key="side" class="bans__col">
                                    <span
                                        v-for="row in detail.suspensions[side]"
                                        :key="`s-${row.player.id}`"
                                        class="bans__chip"
                                        :title="banLabel(row.reason)"
                                    >
                                        <i aria-hidden="true">{{ row.reason === 'red card' ? '🟥' : '🟨' }}</i>
                                        {{ row.player.name }}
                                    </span>
                                    <span
                                        v-for="row in detail.injuries?.[side] ?? []"
                                        :key="`i-${row.player.id}`"
                                        class="bans__chip bans__chip--injury"
                                        :title="t('match.injured', { n: row.matches_left })"
                                    >
                                        <i aria-hidden="true">✚</i>
                                        {{ row.player.name }}
                                        <small>{{ t(row.matches_left > 1 ? 'match.gamesPlural' : 'match.games', { n: row.matches_left }) }}</small>
                                    </span>
                                </div>
                            </div>
                        </section>

                        <template v-if="game.is_played">
                            <section class="timeline" aria-label="Match events">
                                <h3 class="match__subtitle">{{ t('match.timeline') }}</h3>
                                <p v-if="!detail.events.length" class="matches__empty">{{ t('match.quiet') }}</p>
                                <div class="timeline__sides" aria-hidden="true">
                                    <span :style="{ '--team-color': game.home_team.color }">&#9664; {{ game.home_team.code }}</span>
                                    <span :style="{ '--team-color': game.away_team.color }">{{ game.away_team.code }} &#9654;</span>
                                </div>
                                <ol class="timeline__list">
                                    <li
                                        v-for="event in detail.events"
                                        :key="event.id"
                                        class="timeline__item"
                                        :class="[`is-${event.type}`, event.team_id === game.away_team.id ? 'is-away' : 'is-home']"
                                        :style="{ '--team-color': event.team_id === game.away_team.id ? game.away_team.color : game.home_team.color }"
                                    >
                                        <span class="timeline__minute">{{ event.minute }}'</span>
                                        <span class="timeline__icon">{{ eventIcons[event.type] }}</span>
                                        <span class="timeline__text" v-html="renderCommentary(event)"></span>
                                        <b class="timeline__team">{{ event.team_id === game.away_team.id ? game.away_team.code : game.home_team.code }}</b>
                                    </li>
                                </ol>
                            </section>

                            <section class="lineups" aria-label="Lineups">
                                <h3 class="match__subtitle">{{ t('match.lineups') }}</h3>
                                <div class="lineups__grid">
                                    <div v-for="side in ['home', 'away']" :key="side" class="lineups__col">
                                        <h4 class="lineups__team">{{ game[`${side}_team`].code }}</h4>
                                        <div
                                            v-for="row in detail.lineups[side]"
                                            :key="row.player_id"
                                            class="lineups__row"
                                            :class="{ 'is-sub': !row.is_starting }"
                                        >
                                            <span class="lineups__pos">{{ row.position }}</span>
                                            <span class="lineups__name">
                                                {{ row.name }}
                                                <i v-if="row.player_id === motm" class="lineups__motm" :title="t('match.motm')">&#9733;</i>
                                                <span class="lineups__badges" aria-hidden="true">
                                                    <i v-if="playerEvents[row.player_id]?.goals" class="lineups__badge">⚽<small v-if="playerEvents[row.player_id].goals > 1">×{{ playerEvents[row.player_id].goals }}</small></i>
                                                    <i v-if="playerEvents[row.player_id]?.yellow" class="lineups__badge">🟨</i>
                                                    <i v-if="playerEvents[row.player_id]?.red" class="lineups__badge">🟥</i>
                                                    <i v-if="playerEvents[row.player_id]?.injury" class="lineups__badge lineups__badge--injury">✚</i>
                                                </span>
                                                <small v-if="row.came_on" class="lineups__sub is-on">{{ row.came_on }}' &uarr;</small>
                                                <small v-if="row.went_off" class="lineups__sub is-off">{{ row.went_off }}' &darr;</small>
                                            </span>
                                            <b class="lineups__rating" :class="ratingTone(row.rating)">{{ row.rating?.toFixed(1) ?? '–' }}</b>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </template>

                        <p v-else class="match__pending">
                            {{ t('match.pending') }}
                        </p>
                    </template>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
