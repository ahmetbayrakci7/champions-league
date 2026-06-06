<script setup>
import { useLeagueStore } from '../stores/league';
import { t } from '../i18n';
import GameCard from './GameCard.vue';

const league = useLeagueStore();
</script>

<template>
    <section class="panel panel--matches" aria-label="Match results">
        <div class="panel__head">
            <h2 class="panel__title">{{ t('centre.title') }}</h2>
            <span class="panel__badge">{{ t('centre.group', { g: league.selectedGroup }) }}</span>
        </div>

        <nav class="weeks" aria-label="Weeks">
            <button
                v-for="entry in league.weeks"
                :key="entry.week"
                type="button"
                class="weeks__pill"
                :class="{
                    'is-active': Number(entry.week) === Number(league.selectedWeek),
                    'is-played': Number(entry.week) <= league.currentWeek,
                }"
                @click="league.focusWeek(Number(entry.week))"
            >
                {{ t('centre.week', { n: entry.week }) }}
            </button>
        </nav>

        <div class="matches">
            <Transition name="fade" mode="out-in">
                <div :key="league.selectedWeek" class="matches__list">
                    <GameCard
                        v-for="game in league.selectedWeekGames"
                        :key="game.id"
                        :game="game"
                    />
                    <p v-if="!league.selectedWeekGames.length" class="matches__empty">
                        {{ t('centre.empty') }}
                    </p>
                </div>
            </Transition>
        </div>
    </section>
</template>
