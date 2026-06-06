<script setup>
import { computed } from 'vue';
import { useLeagueStore } from '../stores/league';
import { t } from '../i18n';

const league = useLeagueStore();

const champion = computed(() =>
    league.seasonOver && league.standings.length ? league.standings[0].team_id : null,
);
</script>

<template>
    <section class="panel panel--table" aria-label="Group table">
        <div class="panel__head">
            <h2 class="panel__title">{{ t('table.title', { g: league.selectedGroup }) }}</h2>
            <span v-if="champion" class="panel__badge panel__badge--gold">{{ t('table.winner') }}</span>
        </div>

        <div class="table">
            <div class="table__header">
                <span class="table__pos">#</span>
                <span class="table__team">{{ t('table.club') }}</span>
                <span class="table__stat">{{ t('table.p') }}</span>
                <span class="table__stat">{{ t('table.w') }}</span>
                <span class="table__stat">{{ t('table.d') }}</span>
                <span class="table__stat">{{ t('table.l') }}</span>
                <span class="table__stat">{{ t('table.gd') }}</span>
                <span class="table__pts">{{ t('table.pts') }}</span>
            </div>

            <TransitionGroup name="row" tag="div" class="table__body">
                <div
                    v-for="(row, index) in league.standings"
                    :key="row.team_id"
                    class="table__row is-clickable"
                    :class="{ 'is-champion': row.team_id === champion, 'is-leader': index === 0 && league.currentWeek > 0 }"
                    :style="{ '--team-color': row.color }"
                    :title="t('table.viewSquad', { name: row.name })"
                    @click="league.openTeam(row.team_id)"
                >
                    <span class="table__pos">{{ index + 1 }}</span>
                    <span class="table__team">
                        <img v-if="row.logo_url" class="table__logo" :src="row.logo_url" :alt="row.name" loading="lazy">
                        <i v-else class="table__chip" aria-hidden="true"></i>
                        <b class="table__code">{{ row.code }}</b>
                        <span v-if="row.team_id === champion" class="table__crown" :title="t('table.winnerTitle')">&#9818;</span>
                    </span>
                    <span class="table__stat">{{ row.played }}</span>
                    <span class="table__stat">{{ row.won }}</span>
                    <span class="table__stat">{{ row.drawn }}</span>
                    <span class="table__stat">{{ row.lost }}</span>
                    <span class="table__stat" :class="{ 'is-positive': row.goal_difference > 0, 'is-negative': row.goal_difference < 0 }">
                        {{ row.goal_difference > 0 ? '+' : '' }}{{ row.goal_difference }}
                    </span>
                    <span class="table__pts">{{ row.points }}</span>
                </div>
            </TransitionGroup>
        </div>

        <p class="panel__hint">{{ t('table.hint') }}</p>
    </section>
</template>
