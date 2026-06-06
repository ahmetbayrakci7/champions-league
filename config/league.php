<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Simulation tuning
    |--------------------------------------------------------------------------
    |
    | average_total_goals: league-wide expected goals per match, split between
    | the two sides proportionally to their effective strengths.
    | min_expected_goals: floor so even the weakest side keeps a small chance.
    | goalkeeper_dampening: how strongly a keeper suppresses the opponent xG.
    |
    */

    'average_total_goals' => 2.8,
    'min_expected_goals' => 0.20,
    'max_goals_per_side' => 9,
    'goalkeeper_dampening' => 350,
    'supporter_dampening' => 450,

    /*
    |--------------------------------------------------------------------------
    | Championship prediction
    |--------------------------------------------------------------------------
    |
    | Predictions become visible once this many weeks have been played
    | (i.e. the final 3 weeks of a 6-week double round-robin).
    | iterations: Monte Carlo runs over the remaining fixtures.
    |
    */

    'prediction_start_week' => 4,
    'prediction_iterations' => 1000,

    /*
    |--------------------------------------------------------------------------
    | Champions League group stage
    |--------------------------------------------------------------------------
    |
    | 32 clubs in 4 seeding pots of 8 (FAQ #2/#4). The draw builds eight
    | groups of four: one club from each pot, never two clubs from the
    | same association in one group. Squads are imported from the
    | official EA SPORTS FC ratings data (ea_team_id).
    |
    */

    'group_names' => ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'],

    /*
    |--------------------------------------------------------------------------
    | Matchday calendar (FAQ #4)
    |--------------------------------------------------------------------------
    |
    | Each matchday spans Tuesday + Wednesday: four groups play on each
    | day. Clubs from the same association are paired so their matches
    | split between the two days. matchday_dates are the Tuesdays;
    | Wednesday is always the next day. Kickoffs follow UEFA slots.
    |
    */

    'matchday_dates' => [
        '2026-09-15',
        '2026-09-29',
        '2026-10-20',
        '2026-11-03',
        '2026-11-24',
        '2026-12-08',
    ],

    'kickoff_slots' => [
        // Each group's first match of a matchday gets an early-evening
        // slot, the second a late-evening one — always sensible hours.
        'early' => ['17:45', '18:00', '18:30', '18:45', '19:00'],
        'late' => ['20:00', '20:30', '20:45', '21:00', '21:45'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Knockout phase (FAQ #3)
    |--------------------------------------------------------------------------
    |
    | Group winners + runners-up advance. R16/QF/SF are two-legged, the
    | final is a single match on neutral ground. Weeks continue after the
    | 6 group matchdays so fixtures stay globally ordered.
    |
    */

    'knockout_weeks' => [
        'r16' => [7, 8],
        'qf' => [9, 10],
        'sf' => [11, 12],
        'final' => [13],
    ],

    'knockout_dates' => [
        7 => '2027-02-16',
        8 => '2027-03-09',
        9 => '2027-04-06',
        10 => '2027-04-13',
        11 => '2027-04-27',
        12 => '2027-05-04',
        13 => '2027-05-29',
    ],

    /*
    |--------------------------------------------------------------------------
    | Match engine
    |--------------------------------------------------------------------------
    |
    | Minute-by-minute event simulation: bookings, injuries, subs and
    | player ratings. extra_time_factor scales xG for the 30 extra
    | minutes when a knockout tie is level after both legs.
    |
    */

    'engine' => [
        'yellow_mean_per_team' => 1.6,
        'direct_red_chance' => 0.04,
        'injury_chance' => 0.09,
        'assist_chance' => 0.72,
        'subs_per_team' => 3,
        'extra_time_factor' => 0.33,
    ],

    /*
    |--------------------------------------------------------------------------
    | Discipline & form
    |--------------------------------------------------------------------------
    |
    | suspension: a red card (or every 3rd accumulated yellow) bans the
    | player for his team's next match.
    | form: the squad's average match rating over the last N games nudges
    | the team's effective power, so the win odds shift with momentum.
    |
    */

    'suspension' => [
        'yellow_threshold' => 3,
    ],

    'form' => [
        'window' => 3,        // matches considered
        'baseline' => 6.2,    // neutral average rating
        'sensitivity' => 0.05, // power multiplier per rating point
        'min' => 0.90,
        'max' => 1.10,
    ],

    'clubs' => [
        // Pot 1
        ['ea_team_id' => 243, 'name' => 'Real Madrid', 'code' => 'RMA', 'color' => '#FEBE10', 'country' => 'ESP', 'pot' => 1],
        ['ea_team_id' => 10, 'name' => 'Manchester City', 'code' => 'MCI', 'color' => '#6CABDD', 'country' => 'ENG', 'pot' => 1],
        ['ea_team_id' => 21, 'name' => 'Bayern München', 'code' => 'BAY', 'color' => '#DC052D', 'country' => 'GER', 'pot' => 1],
        ['ea_team_id' => 73, 'name' => 'Paris Saint-Germain', 'code' => 'PSG', 'color' => '#004170', 'country' => 'FRA', 'pot' => 1],
        ['ea_team_id' => 9, 'name' => 'Liverpool', 'code' => 'LIV', 'color' => '#C8102E', 'country' => 'ENG', 'pot' => 1],
        ['ea_team_id' => 241, 'name' => 'FC Barcelona', 'code' => 'BAR', 'color' => '#A50044', 'country' => 'ESP', 'pot' => 1],
        ['ea_team_id' => 1, 'name' => 'Arsenal', 'code' => 'ARS', 'color' => '#EF0107', 'country' => 'ENG', 'pot' => 1],
        ['ea_team_id' => 48, 'name' => 'Napoli', 'code' => 'NAP', 'color' => '#199FD6', 'country' => 'ITA', 'pot' => 1],

        // Pot 2
        ['ea_team_id' => 5, 'name' => 'Chelsea', 'code' => 'CHE', 'color' => '#034694', 'country' => 'ENG', 'pot' => 2],
        ['ea_team_id' => 240, 'name' => 'Atlético de Madrid', 'code' => 'ATM', 'color' => '#CB3524', 'country' => 'ESP', 'pot' => 2],
        ['ea_team_id' => 22, 'name' => 'Borussia Dortmund', 'code' => 'BVB', 'color' => '#FDE100', 'country' => 'GER', 'pot' => 2],
        ['ea_team_id' => 45, 'name' => 'Juventus', 'code' => 'JUV', 'color' => '#D6CDBE', 'country' => 'ITA', 'pot' => 2],
        ['ea_team_id' => 32, 'name' => 'Bayer Leverkusen', 'code' => 'B04', 'color' => '#E32221', 'country' => 'GER', 'pot' => 2],
        ['ea_team_id' => 234, 'name' => 'SL Benfica', 'code' => 'BEN', 'color' => '#E83030', 'country' => 'POR', 'pot' => 2],
        ['ea_team_id' => 236, 'name' => 'FC Porto', 'code' => 'FCP', 'color' => '#003E7E', 'country' => 'POR', 'pot' => 2],
        ['ea_team_id' => 237, 'name' => 'Sporting CP', 'code' => 'SCP', 'color' => '#008057', 'country' => 'POR', 'pot' => 2],

        // Pot 3
        ['ea_team_id' => 13, 'name' => 'Newcastle United', 'code' => 'NEW', 'color' => '#41B6E6', 'country' => 'ENG', 'pot' => 3],
        ['ea_team_id' => 448, 'name' => 'Athletic Club', 'code' => 'ATH', 'color' => '#EE2523', 'country' => 'ESP', 'pot' => 3],
        ['ea_team_id' => 112172, 'name' => 'RB Leipzig', 'code' => 'RBL', 'color' => '#DD0741', 'country' => 'GER', 'pot' => 3],
        ['ea_team_id' => 69, 'name' => 'AS Monaco', 'code' => 'MON', 'color' => '#E63031', 'country' => 'FRA', 'pot' => 3],
        ['ea_team_id' => 219, 'name' => 'Olympique de Marseille', 'code' => 'MAR', 'color' => '#2FAEE0', 'country' => 'FRA', 'pot' => 3],
        ['ea_team_id' => 86, 'name' => 'Rangers', 'code' => 'RAN', 'color' => '#1B458F', 'country' => 'SCO', 'pot' => 3],
        ['ea_team_id' => 231, 'name' => 'Club Brugge', 'code' => 'CLB', 'color' => '#0066B2', 'country' => 'BEL', 'pot' => 3],
        ['ea_team_id' => 280, 'name' => 'Olympiacos', 'code' => 'OLY', 'color' => '#D6001C', 'country' => 'GRE', 'pot' => 3],

        // Pot 4
        ['ea_team_id' => 325, 'name' => 'Galatasaray', 'code' => 'GAL', 'color' => '#FDB912', 'country' => 'TUR', 'pot' => 4],
        ['ea_team_id' => 326, 'name' => 'Fenerbahçe', 'code' => 'FEN', 'color' => '#163962', 'country' => 'TUR', 'pot' => 4],
        ['ea_team_id' => 78, 'name' => 'Celtic', 'code' => 'CEL', 'color' => '#018749', 'country' => 'SCO', 'pot' => 4],
        ['ea_team_id' => 2014, 'name' => 'Union Saint-Gilloise', 'code' => 'USG', 'color' => '#FFDD00', 'country' => 'BEL', 'pot' => 4],
        ['ea_team_id' => 819, 'name' => 'FC København', 'code' => 'FCK', 'color' => '#10316F', 'country' => 'DEN', 'pot' => 4],
        ['ea_team_id' => 266, 'name' => 'Slavia Praha', 'code' => 'SLA', 'color' => '#E4002B', 'country' => 'CZE', 'pot' => 4],
        ['ea_team_id' => 918, 'name' => 'Bodø/Glimt', 'code' => 'BOD', 'color' => '#FFD500', 'country' => 'NOR', 'pot' => 4],
        ['ea_team_id' => 113888, 'name' => 'Qarabağ FK', 'code' => 'QAR', 'color' => '#1C66B5', 'country' => 'AZE', 'pot' => 4],
    ],
];
