<script setup>
import { computed, ref } from 'vue';
import { useLeagueStore } from '../stores/league';
import { t, i18n } from '../i18n';

const props = defineProps({
    game: { type: Object, required: true },
});

const league = useLeagueStore();

const kickoff = computed(() => {
    if (!props.game.kickoff_at) return null;

    // Reactive read of i18n.locale: day/month names flip with the language.
    const locale = i18n.locale === 'tr' ? 'tr-TR' : 'en-GB';
    const date = new Date(props.game.kickoff_at);

    return {
        wednesday: date.getDay() === 3,
        day: date.toLocaleDateString(locale, { weekday: 'short' }).toUpperCase(),
        date: date.toLocaleDateString(locale, { day: 'numeric', month: 'short' }).toUpperCase(),
        time: date.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' }),
    };
});

const editing = ref(false);
const homeGoals = ref(0);
const awayGoals = ref(0);

function startEdit() {
    homeGoals.value = props.game.home_goals;
    awayGoals.value = props.game.away_goals;
    editing.value = true;
}

async function save() {
    await league.updateGame(props.game.id, homeGoals.value, awayGoals.value);
    editing.value = false;
}

function clamp(value) {
    return Math.max(0, Math.min(99, Number(value) || 0));
}
</script>

<template>
    <article
        class="game is-openable"
        :class="{ 'is-played': game.is_played, 'is-editing': editing }"
        :title="game.is_played ? t('centre.summary') : t('centre.preview')"
        @click="editing ? null : league.openMatch(game.id)"
    >
        <div v-if="kickoff" class="game__kickoff" :class="{ 'is-wednesday': kickoff.wednesday }">
            <b>{{ kickoff.day }}</b>
            <span>{{ kickoff.date }}</span>
            <i>{{ kickoff.time }}</i>
        </div>

        <div class="game__side game__side--home" :style="{ '--team-color': game.home_team.color }">
            <img v-if="game.home_team.logo_url" class="game__logo" :src="game.home_team.logo_url" :alt="game.home_team.name" loading="lazy">
            <i v-else class="game__chip" aria-hidden="true"></i>
            <span class="game__code">{{ game.home_team.code }}</span>
            <span class="game__name">{{ game.home_team.name }}</span>
        </div>

        <div class="game__score">
            <template v-if="editing">
                <input
                    v-model.number="homeGoals"
                    class="game__input"
                    type="number"
                    min="0"
                    max="99"
                    aria-label="Home goals"
                    @change="homeGoals = clamp(homeGoals)"
                >
                <span class="game__dash">:</span>
                <input
                    v-model.number="awayGoals"
                    class="game__input"
                    type="number"
                    min="0"
                    max="99"
                    aria-label="Away goals"
                    @change="awayGoals = clamp(awayGoals)"
                >
            </template>
            <template v-else-if="game.is_played">
                <span class="game__goals">{{ game.home_goals }}</span>
                <span class="game__dash">:</span>
                <span class="game__goals">{{ game.away_goals }}</span>
            </template>
            <span v-else class="game__vs">VS</span>
        </div>

        <div class="game__side game__side--away" :style="{ '--team-color': game.away_team.color }">
            <span class="game__name">{{ game.away_team.name }}</span>
            <span class="game__code">{{ game.away_team.code }}</span>
            <img v-if="game.away_team.logo_url" class="game__logo" :src="game.away_team.logo_url" :alt="game.away_team.name" loading="lazy">
            <i v-else class="game__chip" aria-hidden="true"></i>
        </div>

        <div class="game__actions" @click.stop>
            <template v-if="editing">
                <button type="button" class="game__btn game__btn--save" :disabled="league.busyAction === 'edit'" @click="save">&#10003;</button>
                <button type="button" class="game__btn" @click="editing = false">&#10005;</button>
            </template>
            <button
                v-else-if="game.is_played"
                type="button"
                class="game__btn game__btn--edit"
                :title="t('centre.editScore')"
                @click="startEdit"
            >&#9998;</button>
        </div>
    </article>
</template>
