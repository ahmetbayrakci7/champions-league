<script setup>
import { useLeagueStore } from '../stores/league';
import { t } from '../i18n';

const league = useLeagueStore();
</script>

<template>
    <section class="panel panel--predictions" aria-label="Championship predictions">
        <div class="panel__head">
            <h2 class="panel__title">{{ t('odds.title') }}</h2>
            <span v-if="league.predictionsUnlocked" class="panel__badge">{{ t('odds.badge') }}</span>
        </div>

        <div v-if="!league.predictionsUnlocked" class="locked">
            <span class="locked__icon" aria-hidden="true">&#128274;</span>
            <p class="locked__title">{{ t('odds.lockedTitle') }}</p>
            <p class="locked__text" v-html="t('odds.lockedText', { b: `<b>${t('odds.matchday4')}</b>` })"></p>
        </div>

        <TransitionGroup v-else name="row" tag="div" class="odds">
            <div
                v-for="row in league.rankedPredictions"
                :key="row.team_id"
                class="odds__row"
                :class="{ 'is-out': row.percent === 0, 'is-locked-in': row.percent === 100 }"
                :style="{ '--team-color': row.color }"
            >
                <div class="odds__meta">
                    <b class="odds__code">{{ row.code }}</b>
                    <span class="odds__name">{{ row.name }}</span>
                    <span class="odds__value">{{ row.percent }}<small>%</small></span>
                </div>
                <div class="odds__track">
                    <div class="odds__bar" :style="{ width: `${row.percent}%` }"></div>
                </div>
            </div>
        </TransitionGroup>
    </section>
</template>
