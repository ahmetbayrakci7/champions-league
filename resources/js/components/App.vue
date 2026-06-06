<script setup>
import { onMounted } from 'vue';
import { useLeagueStore } from '../stores/league';
import { t } from '../i18n';
import LeagueHeader from './LeagueHeader.vue';
import DrawStage from './DrawStage.vue';
import GroupTabs from './GroupTabs.vue';
import LeagueTable from './LeagueTable.vue';
import MatchCenter from './MatchCenter.vue';
import PredictionPanel from './PredictionPanel.vue';
import KnockoutBoard from './KnockoutBoard.vue';
import StatsBoard from './StatsBoard.vue';
import ControlBar from './ControlBar.vue';
import TeamModal from './TeamModal.vue';
import MatchModal from './MatchModal.vue';

const league = useLeagueStore();

onMounted(() => league.fetchState());
</script>

<template>
    <div class="pitch">
        <div class="pitch__glow pitch__glow--left" aria-hidden="true"></div>
        <div class="pitch__glow pitch__glow--right" aria-hidden="true"></div>
        <div class="pitch__circle" aria-hidden="true"></div>

        <main class="layout">
            <LeagueHeader />

            <div v-if="league.loading" class="loading">
                <span class="loading__ball"></span>
                <span class="loading__text">{{ t('loading.warmup') }}</span>
            </div>

            <DrawStage v-else-if="!league.drawn" />

            <template v-else>
                <GroupTabs class="reveal" style="--delay: 0ms" />

                <div v-if="league.view === 'groups'" class="layout__grid">
                    <LeagueTable class="reveal" style="--delay: 60ms" />
                    <MatchCenter class="reveal" style="--delay: 140ms" />
                    <PredictionPanel class="reveal" style="--delay: 220ms" />
                </div>

                <KnockoutBoard v-else-if="league.view === 'knockout'" class="reveal" style="--delay: 60ms" />

                <StatsBoard v-else class="reveal" style="--delay: 60ms" />
            </template>

            <ControlBar v-if="!league.loading && league.drawn && league.view !== 'stats'" />
            <TeamModal />
            <MatchModal />
        </main>
    </div>
</template>
