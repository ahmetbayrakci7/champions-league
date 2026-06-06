<script setup>
import { useLeagueStore } from '../stores/league';
import { t } from '../i18n';

const league = useLeagueStore();

const playerBoards = [
    ['scorers', 'stats.scorers', '⚽'],
    ['assists', 'stats.assists', '🎯'],
    ['contributions', 'stats.contributions', '⚡'],
    ['ratings', 'stats.ratings', '★'],
    ['cards', 'stats.cards', '🟨'],
    ['reds', 'stats.reds', '🟥'],
];

const teamBoards = [
    ['attack', 'stats.attack', 'stats.attackHint'],
    ['defence', 'stats.defence', 'stats.defenceHint'],
    ['clean_sheets', 'stats.cleanSheets', 'stats.cleanSheetsHint'],
    ['cards', 'stats.teamCards', 'stats.teamCardsHint'],
];
</script>

<template>
    <section class="panel statsboard" aria-label="Tournament statistics">
        <div class="panel__head">
            <h2 class="panel__title">{{ t('stats.title') }}</h2>
            <span v-if="league.stats?.played_games" class="panel__badge">{{ t('stats.gamesPlayed', { n: league.stats.played_games }) }}</span>
        </div>

        <div v-if="league.statsLoading" class="loading loading--modal">
            <span class="loading__ball"></span>
            <span class="loading__text">{{ t('loading.stats') }}</span>
        </div>

        <div v-else-if="!league.stats || !league.stats.players" class="locked">
            <span class="locked__icon" aria-hidden="true">&#128202;</span>
            <p class="locked__title">{{ t('stats.noDataTitle') }}</p>
            <p class="locked__text">{{ t('stats.noDataText') }}</p>
        </div>

        <template v-else>
            <div class="statsboard__grid">
                <article v-for="[key, label, icon] in playerBoards" :key="key" class="statcard">
                    <h3 class="statcard__title"><span aria-hidden="true">{{ icon }}</span> {{ t(label) }}</h3>
                    <ol class="statcard__list">
                        <li v-for="(row, index) in league.stats.players[key]" :key="row.player_id ?? index" class="statcard__row">
                            <span class="statcard__rank">{{ index + 1 }}</span>
                            <img v-if="row.avatar_url" class="statcard__avatar" :src="row.avatar_url" :alt="row.name" loading="lazy">
                            <span class="statcard__name">
                                {{ row.name }}
                                <small v-if="row.team">{{ row.team.code }}</small>
                            </span>
                            <b class="statcard__value">{{ row.value }}</b>
                        </li>
                    </ol>
                </article>

                <article v-for="[key, label, hint] in teamBoards" :key="key" class="statcard">
                    <h3 class="statcard__title">{{ t(label) }} <small>{{ t(hint) }}</small></h3>
                    <ol class="statcard__list">
                        <li v-for="(row, index) in league.stats.teams[key]" :key="row.team?.id ?? index" class="statcard__row">
                            <span class="statcard__rank">{{ index + 1 }}</span>
                            <img v-if="row.team?.logo_url" class="statcard__avatar statcard__avatar--logo" :src="row.team.logo_url" :alt="row.team.name" loading="lazy">
                            <span class="statcard__name">{{ row.team?.name }}</span>
                            <b class="statcard__value">{{ row.value }}</b>
                        </li>
                    </ol>
                </article>

                <article v-if="league.stats.teams.biggest_win" class="statcard statcard--highlight">
                    <h3 class="statcard__title">{{ t('stats.biggestWin') }}</h3>
                    <div class="statcard__big">
                        <span>{{ league.stats.teams.biggest_win.home_team.code }}</span>
                        <b>{{ league.stats.teams.biggest_win.home_goals }} – {{ league.stats.teams.biggest_win.away_goals }}</b>
                        <span>{{ league.stats.teams.biggest_win.away_team.code }}</span>
                    </div>
                </article>
            </div>
        </template>
    </section>
</template>
