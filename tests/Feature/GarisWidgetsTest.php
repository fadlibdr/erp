<?php

declare(strict_types=1);

use App\Filament\Widgets\ProjectEtiketWidget;
use App\Filament\Widgets\SCurveWidget;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('renders the GARIS S-curve widget', function () {
    Livewire::test(SCurveWidget::class)
        ->assertOk()
        ->assertSee('Realisasi 62,4%');
});

it('renders the GARIS etiket widget', function () {
    Livewire::test(ProjectEtiketWidget::class)
        ->assertOk()
        ->assertSee('BAST-1');
});
