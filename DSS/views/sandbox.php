<?php

require_once '../includes/header.php';
?>

<?php if ($sessionRole !== 'Executive Board'): ?>
    <div class="card">
        <div class="card-content">
            <span class="card-title" style="color: var(--critical);"><?= __('access_restricted') ?></span>
            <p class="grey-text"><?= __('no_permission_desc') ?></p>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-content">
            <span class="card-title"><i class="material-icons">science</i> <?= __('try_out_tool') ?></span>
            <p class="grey-text"><?= __('test_different_budget') ?></p>
        </div>
    </div>

    <div class="card">
        <div class="card-content">
            <span class="card-title"><i class="material-icons">science</i> <?= __('try_out_tool') ?></span>
            <p class="grey-text">
                <?= __('test_different_settings') ?>
            </p>
            <form id="sandboxForm">
                <?= $csrfField ?>
                <div class="row">
                    <div class="input-field col s12 m6">
                        <input
                            type="number"
                            id="sim_budget"
                            name="sim_budget"
                            min="0"
                            step="0.01"
                            required
                        >
                        <label for="sim_budget">Try a Budget (GHS)</label>
                    </div>
                    <div class="input-field col s12 m6">
                        <input
                            type="number"
                            id="sim_hours"
                            name="sim_hours"
                            min="1"
                            step="1"
                            required
                        >
                        <label for="sim_hours">Try Hours</label>
                    </div>
                    <div class="importance-group col s12 m6">
                        <div class="importance-group-label">Reach Priority</div>
                        <div class="importance-options">
                            <label class="importance-pill"><input type="radio" name="sim_reach_weight" value="1" required><span>Slightly</span></label>
                            <label class="importance-pill"><input type="radio" name="sim_reach_weight" value="2" checked><span>Moderately</span></label>
                            <label class="importance-pill"><input type="radio" name="sim_reach_weight" value="3"><span>Very</span></label>
                            <label class="importance-pill"><input type="radio" name="sim_reach_weight" value="4"><span>Extremely</span></label>
                        </div>
                        <span class="helper-text">How much we care about how many students benefit</span>
                    </div>
                    <div class="importance-group col s12 m6">
                        <div class="importance-group-label">Speed Priority</div>
                        <div class="importance-options">
                            <label class="importance-pill"><input type="radio" name="sim_speed_weight" value="1" required><span>Slightly</span></label>
                            <label class="importance-pill"><input type="radio" name="sim_speed_weight" value="2" checked><span>Moderately</span></label>
                            <label class="importance-pill"><input type="radio" name="sim_speed_weight" value="3"><span>Very</span></label>
                            <label class="importance-pill"><input type="radio" name="sim_speed_weight" value="4"><span>Extremely</span></label>
                        </div>
                        <span class="helper-text">How much we care about finishing fast</span>
                    </div>
                </div>
                <button type="submit" class="btn green" id="sandboxSubmitBtn"><i class="material-icons left">play_arrow</i>Run Test</button>
            </form>

            <div id="sandboxResultsContainer" style="display:none; margin-top: 1.5rem;"></div>
        </div>
    </div>

    <div class="card">
        <div class="card-content">
            <span class="card-title"><i class="material-icons">search</i>Budget Finder</span>
            <p class="grey-text">Find the minimum budget needed to reach a target project score.</p>
            <form id="goalSeekingForm">
                <?= $csrfField ?>
                <div class="row">
                    <div class="input-field col s12">
                        <input type="number" id="target_pis" name="target_pis" min="0.1" step="0.0001" placeholder="e.g., 2.5000" required>
                        <label for="target_pis">Target Score</label>
                    </div>
                </div>
                <button type="submit" class="btn green" id="goalBtn"><i class="material-icons left">search</i>Find Budget</button>
            </form>
            <div id="goalResult" class="card-panel" style="margin-top: 1.5rem; display: none;"></div>
        </div>
    </div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>