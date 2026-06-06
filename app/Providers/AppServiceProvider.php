<?php

namespace App\Providers;

use App\Services\ChampionshipPredictor;
use App\Services\Contracts\ChampionshipPredictorInterface;
use App\Services\Contracts\DrawServiceInterface;
use App\Services\Contracts\FixtureGeneratorInterface;
use App\Services\Contracts\InjuryServiceInterface;
use App\Services\Contracts\KnockoutServiceInterface;
use App\Services\Contracts\LeagueServiceInterface;
use App\Services\Contracts\LineupSelectorInterface;
use App\Services\Contracts\MatchEngineInterface;
use App\Services\Contracts\MatchSchedulerInterface;
use App\Services\Contracts\MatchSimulatorInterface;
use App\Services\Contracts\RandomGeneratorInterface;
use App\Services\Contracts\StandingsCalculatorInterface;
use App\Services\Contracts\StatsServiceInterface;
use App\Services\Contracts\SuspensionServiceInterface;
use App\Services\Contracts\TeamFormServiceInterface;
use App\Services\DrawService;
use App\Services\FixtureGenerator;
use App\Services\InjuryService;
use App\Services\KnockoutService;
use App\Services\LeagueService;
use App\Services\LineupSelector;
use App\Services\MatchEngine;
use App\Services\MatchScheduler;
use App\Services\MatchSimulator;
use App\Services\StandingsCalculator;
use App\Services\StatsService;
use App\Services\Support\MtRandomGenerator;
use App\Services\SuspensionService;
use App\Services\TeamFormService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(RandomGeneratorInterface::class, MtRandomGenerator::class);
        $this->app->bind(DrawServiceInterface::class, DrawService::class);
        $this->app->bind(FixtureGeneratorInterface::class, FixtureGenerator::class);
        $this->app->bind(MatchSchedulerInterface::class, MatchScheduler::class);
        $this->app->bind(MatchSimulatorInterface::class, MatchSimulator::class);
        $this->app->bind(LineupSelectorInterface::class, LineupSelector::class);
        $this->app->bind(MatchEngineInterface::class, MatchEngine::class);
        $this->app->bind(StandingsCalculatorInterface::class, StandingsCalculator::class);
        $this->app->bind(ChampionshipPredictorInterface::class, ChampionshipPredictor::class);
        $this->app->bind(KnockoutServiceInterface::class, KnockoutService::class);
        $this->app->bind(StatsServiceInterface::class, StatsService::class);
        $this->app->bind(SuspensionServiceInterface::class, SuspensionService::class);
        $this->app->bind(InjuryServiceInterface::class, InjuryService::class);
        $this->app->bind(TeamFormServiceInterface::class, TeamFormService::class);
        $this->app->bind(LeagueServiceInterface::class, LeagueService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
