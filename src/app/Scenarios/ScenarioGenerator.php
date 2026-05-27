<?php

namespace App\Scenarios;

/**
 * Scenario generation driver の interface。
 */
interface ScenarioGenerator
{
    /**
     * Scenario generation input から scenario を生成する。
     */
    public function generate(ScenarioGenerationInput $input): ScenarioGenerationResult;
}
