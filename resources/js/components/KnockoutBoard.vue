<script setup>
import { computed } from 'vue';
import { useLeagueStore } from '../stores/league';
import { t } from '../i18n';

const league = useLeagueStore();

function sideGoals(tie, game, teamId) {
    if (!game) return '–';

    return game.home_team_id === teamId ? game.home_goals : game.away_goals;
}

const nextLabel = computed(() => {
    const next = league.knockout?.next;

    if (!next) return null;

    if (next.action === 'draw') return t('ko.draw_r16');
    if (next.action === 'done') return t('ko.done');

    return next.leg
        ? t('ko.play', { stage: t(`stage.${next.stage}`), leg: next.leg })
        : t('ko.playSingle', { stage: t(`stage.${next.stage}`) });
});
</script>

<template>
    <section class="panel bracket" aria-label="Knockout bracket">
        <div class="panel__head">
            <h2 class="panel__title">{{ t('ko.title') }}</h2>
            <span v-if="league.knockout?.champion" class="panel__badge panel__badge--gold">
                {{ t('ko.champions', { name: league.knockout.champion.name.toUpperCase() }) }}
            </span>
        </div>

        <div v-if="!league.knockout?.available" class="locked">
            <span class="locked__icon" aria-hidden="true">&#128274;</span>
            <p class="locked__title">{{ t('ko.lockedTitle') }}</p>
            <p class="locked__text" v-html="t('ko.lockedText', { b: `<b>${t('ko.sixMatchdays')}</b>` })"></p>
        </div>

        <template v-else>
            <div v-if="league.knockout.champion" class="bracket__champion">
                <img v-if="league.knockout.champion.logo_url" :src="league.knockout.champion.logo_url" :alt="league.knockout.champion.name">
                <div>
                    <small>{{ t('ko.championsOf') }}</small>
                    <b>{{ league.knockout.champion.name }}</b>
                </div>
                <span aria-hidden="true">&#127942;</span>
            </div>

            <div v-if="!league.knockout.drawn" class="locked">
                <p class="locked__title">{{ t('ko.readyTitle') }}</p>
                <p class="locked__text">{{ t('ko.readyText') }}</p>
            </div>

            <div v-else class="bracket__stages">
                <div v-for="stage in league.knockout.stages" :key="stage.stage" class="bracket__stage">
                    <h3 class="bracket__stage-title">{{ t(`stage.${stage.stage}`) }}</h3>

                    <article
                        v-for="tie in stage.ties"
                        :key="tie.id"
                        class="tie"
                        :class="{ 'is-decided': tie.winner_team_id }"
                    >
                        <div class="tie__line">
                            <div
                                class="tie__side"
                                :class="{ 'is-winner': tie.winner_team_id === tie.home_team.id }"
                                :style="{ '--team-color': tie.home_team.color }"
                            >
                                <img v-if="tie.home_team.logo_url" class="tie__logo" :src="tie.home_team.logo_url" :alt="tie.home_team.name">
                                <b class="tie__code">{{ tie.home_team.code }}</b>
                            </div>

                            <div class="tie__centre">
                                <b class="tie__aggregate">
                                    {{ tie.aggregate.home }}<i>:</i>{{ tie.aggregate.away }}
                                </b>
                                <span class="tie__legs">
                                    <button
                                        v-for="game in tie.games"
                                        :key="game.id"
                                        type="button"
                                        class="tie__leg"
                                        :title="game.is_played ? t('centre.summary') : t('centre.preview')"
                                        @click="league.openMatch(game.id)"
                                    >
                                        <small v-if="tie.games.length > 1">L{{ game.leg }}</small>
                                        {{ game.is_played
                                            ? `${sideGoals(tie, game, tie.home_team.id)}-${sideGoals(tie, game, tie.away_team.id)}`
                                            : '·' }}
                                    </button>
                                </span>
                                <span v-if="tie.penalties" class="tie__pens">
                                    {{ t('ko.pens', { h: tie.penalties.home, a: tie.penalties.away }) }}
                                </span>
                            </div>

                            <div
                                class="tie__side tie__side--away"
                                :class="{ 'is-winner': tie.winner_team_id === tie.away_team.id }"
                                :style="{ '--team-color': tie.away_team.color }"
                            >
                                <b class="tie__code">{{ tie.away_team.code }}</b>
                                <img v-if="tie.away_team.logo_url" class="tie__logo" :src="tie.away_team.logo_url" :alt="tie.away_team.name">
                            </div>
                        </div>
                    </article>
                </div>
            </div>

            <div class="bracket__actions">
                <button
                    v-if="league.knockout.next && league.knockout.next.action !== 'done'"
                    type="button"
                    class="controls__btn controls__btn--primary"
                    :disabled="!!league.busyAction"
                    @click="league.advanceKnockout()"
                >
                    <span v-if="league.busyAction === 'knockout'" class="controls__spinner" aria-hidden="true"></span>
                    <template v-else>{{ nextLabel }} <span aria-hidden="true">&#9654;</span></template>
                </button>
            </div>
        </template>
    </section>
</template>
