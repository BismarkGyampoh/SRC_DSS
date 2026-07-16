        </main>

    <footer class="app-footer">
        <div class="container">
            <p style="margin: 0; color: rgba(255,255,255,0.7);">
                &copy; <?= date('Y') ?> <a href="https://www.umat.edu.gh" target="_blank">University of Mines and Technology (UMaT)</a> — Students' Representative Council. All rights reserved.
            </p>
            <p style="margin: 0.25rem 0 0; color: rgba(255,255,255,0.5); font-size: 0.75rem;">
                SRC Project Selection Decision Support System &middot; Built for transparent, data-driven project funding
            </p>
        </div>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
    <script>
        document.getElementById('sandboxForm')?.addEventListener('submit', function(e) {
            e.preventDefault();

            const submitBtn = document.getElementById('sandboxSubmitBtn');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Calculating...';
            submitBtn.disabled = true;

            const formData = new FormData(this);
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 15000);

            fetch('../controllers/model_management/action_run_sandbox.php', {
                method: 'POST',
                body: formData,
                signal: controller.signal
            })
            .then(response => {
                clearTimeout(timeoutId);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    renderSandboxResults(data.data);
                } else {
                    M.toast({html: 'An error occurred during simulation.', classes: 'red'});
                }
            })
            .catch(error => {
                clearTimeout(timeoutId);
                console.error('Error:', error);
                M.toast({html: 'Network or Server Error: Please check your connection or contact the System Administrator.', classes: 'red'});
            })
            .finally(() => {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        });

        function renderSandboxResults(data) {
            const container = document.getElementById('sandboxResultsContainer');
            const accepted = data.accepted || [];
            const rejected = data.rejected || [];
            const deferred = data.deferred || [];
            const totalBudgetUsed = toSafeFloat(data.budget_used, 2);
            const totalHoursUsed = toSafeInt(data.hours_used);
            const totalPis = toSafeFloat(data.total_pis, 4);

            if (accepted.length === 0 && rejected.length === 0 && deferred.length === 0) {
                container.innerHTML = '<div class="center-align" style="padding: 3rem 1rem;">'
                    + '<i class="material-icons large grey-text text-lighten-2">science</i>'
                    + '<h5 class="grey-text">No Pending Projects</h5>'
                    + '<p class="grey-text">There are no pending projects available for sandbox simulation.</p>'
                    + '</div>';
                container.style.display = 'block';
                return;
            }

            let html = '<div class="sandbox-report" style="border: 2px solid #f59e0b; border-radius: 12px; padding: 1.5rem; background: #fffbeb;">';
            html += '<div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">';
            html += '<span style="background: #f59e0b; color: #ffffff; padding: 0.375rem 0.75rem; border-radius: 6px; font-size: 0.8125rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">SIMULATION ONLY</span>';
            html += '<h2 style="margin: 0; font-size: 1.125rem; color: #92400e;">Sandbox Mode — Sensitivity Analysis</h2>';
            html += '</div>';

            html += '<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;max-width:720px;margin-bottom:1.5rem;background:#ffffff;">';
            html += '<thead><tr style="background:#fef3c7;"><th colspan="2">Simulated Resource Utilization</th></tr></thead>';
            html += '<tbody>';
            html += '<tr><td>Total Budget Used</td><td>' + totalBudgetUsed.toFixed(2) + ' GHS</td></tr>';
            html += '<tr><td>Total Volunteer Hours Used</td><td>' + totalHoursUsed + ' hrs</td></tr>';
            html += '<tr><td>Combined Simulated PIS</td><td>' + totalPis.toFixed(4) + '</td></tr>';
            html += '</tbody></table>';

            html += buildSandboxTable('Simulated Accepted Projects', accepted, '#d1fae5');
            html += buildSandboxTable('Simulated Rejected Projects', rejected, '#fee2e2');
            html += buildSandboxTable('Simulated Deferred Projects (Rollover Queue)', deferred, '#eff6ff');

            html += '<p style="font-size:0.8125rem;color:#b45309;margin-top:1rem;font-style:italic;">';
            html += 'This is a simulation report only. No database changes were made. Use this for what-if analysis before running the official optimization engine.';
            html += '</p>';
            html += '</div>';

            container.innerHTML = html;
            container.style.display = 'block';
        }

        function buildSandboxTable(title, projects, bgColor) {
            let html = '<h3 style="margin:1.25rem 0 0.5rem;font-size:1rem;color:#374151;">' + escapeHtml(title) + '</h3>';

            if (projects.length === 0) {
                html += '<div class="center-align" style="padding: 3rem 1rem;">';
                html += '<i class="material-icons large grey-text text-lighten-2">folder_open</i>';
                html += '<h5 class="grey-text" style="font-size: 1.1rem;">No Projects</h5>';
                html += '<p class="grey-text" style="font-size: 0.85rem;">There are currently no projects in this category.</p>';
                html += '</div>';
                return html;
            }

            html += '<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;margin-bottom:1rem;">';
            html += '<thead style="background:' + bgColor + ';">';
            html += '<tr>';
            html += '<th>ID</th><th>Title</th><th>Budget (GHS)</th><th>Hours</th>';
            html += '<th>Student Reach</th><th>Weeks</th><th>Simulated PIS</th>';
            html += '</tr>';
            html += '</thead>';
            html += '<tbody>';

            projects.forEach(project => {
                html += '<tr>';
                html += '<td>' + toSafeInt(project.project_id) + '</td>';
                html += '<td>' + escapeHtml(project.title ?? '') + '</td>';
                html += '<td>' + toSafeFloat(project.budget_required, 2).toFixed(2) + '</td>';
                html += '<td>' + toSafeInt(project.volunteer_hours) + '</td>';
                html += '<td>' + toSafeInt(project.student_reach) + '</td>';
                html += '<td>' + toSafeInt(project.implementation_weeks) + '</td>';
                html += '<td>' + toSafeFloat(project.simulated_pis, 4).toFixed(4) + '</td>';
                html += '</tr>';
            });

            html += '</tbody></table>';
            return html;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function toSafeInt(value) {
            const parsed = Number.parseInt(value, 10);
            return Number.isFinite(parsed) ? parsed : 0;
        }

        function toSafeFloat(value, decimals = 2) {
            const parsed = Number.parseFloat(value);
            if (!Number.isFinite(parsed)) {
                return 0;
            }

            return Number(parsed.toFixed(decimals));
        }

        document.getElementById('goalSeekingForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('goalBtn');
            const resultDiv = document.getElementById('goalResult');
            
            btn.innerText = 'Calculating...';
            btn.disabled = true;

            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 15000);

            fetch('../controllers/model_management/action_run_goalseeking.php', {
                method: 'POST',
                body: new FormData(this),
                signal: controller.signal
            })
            .then(response => {
                clearTimeout(timeoutId);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                btn.innerText = 'Run Goal-Seek';
                btn.disabled = false;
                resultDiv.style.display = 'block';
                
                if(data.status === 'error') {
                    resultDiv.innerHTML = `<p style="color:red;">${data.message}</p>`;
                    return;
                }
                
                const res = data.data;
                let html = `<h4 style="color:#025928; margin-bottom:0.5rem;">Goal-Seeking Complete</h4>`;
                if(!res.success) {
                    html += `<p style="color:#b45309;"><strong>Warning:</strong> Not enough pending projects to reach the target PIS of ${toSafeFloat(res.target_pis, 4).toFixed(4)}. Max possible shown below.</p>`;
                }
                html += `<p><strong>Minimum Required Budget:</strong> ${toSafeFloat(res.required_budget, 2).toFixed(2)} GHS</p>`;
                html += `<p><strong>Achieved PIS:</strong> ${toSafeFloat(res.achieved_pis, 4).toFixed(4)}</p>`;

                if (!res.projects || res.projects.length === 0) {
                    html += `<div class="center-align" style="padding: 2rem 1rem;">`;
                    html += `<i class="material-icons large grey-text text-lighten-2">search_off</i>`;
                    html += `<h5 class="grey-text">No Projects Required</h5>`;
                    html += `<p class="grey-text">No pending projects were needed to satisfy this target.</p>`;
                    html += `</div>`;
                } else {
                    html += `<table class="striped highlight responsive-table" style="margin-top:1rem;">`;
                    html += `<thead><tr>`;
                    html += `<th>ID</th><th>Title</th><th class="text-right">Budget (GHS)</th>`;
                    html += `<th class="text-right">Hours</th><th class="text-right">Student Reach</th>`;
                    html += `<th class="text-right">Weeks</th><th class="text-right">PIS</th>`;
                    html += `</tr></thead><tbody>`;
                    res.projects.forEach(project => {
                        html += `<tr>`;
                        html += `<td>${toSafeInt(project.project_id)}</td>`;
                        html += `<td>${escapeHtml(project.title ?? '')}</td>`;
                        html += `<td class="text-right">${toSafeFloat(project.budget_required, 2).toFixed(2)}</td>`;
                        html += `<td class="text-right">${toSafeInt(project.volunteer_hours)}</td>`;
                        html += `<td class="text-right">${toSafeInt(project.student_reach)}</td>`;
                        html += `<td class="text-right">${toSafeInt(project.implementation_weeks)}</td>`;
                        html += `<td class="text-right">${toSafeFloat(project.calculated_pis, 4).toFixed(4)}</td>`;
                        html += `</tr>`;
                    });
                    html += `</tbody></table>`;
                }
                
                resultDiv.innerHTML = html;
            })
            .catch(err => {
                clearTimeout(timeoutId);
                btn.innerText = 'Run Goal-Seek';
                btn.disabled = false;
                M.toast({html: 'Network or Server Error: Please check your connection or contact the System Administrator.', classes: 'red'});
            });
        });

        document.getElementById('runAiBtn')?.addEventListener('click', function(e) {
            const btn = this;
            const resultDiv = document.getElementById('aiResult');
            
            btn.innerText = 'Analyzing Knowledge Base...';
            btn.disabled = true;

            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 15000);

            const csrfToken = document.querySelector('input[name="csrf_token"]').value;
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);

            fetch('../controllers/model_management/action_run_expert_system.php', {
                method: 'POST',
                body: formData,
                signal: controller.signal
            })
            .then(response => {
                clearTimeout(timeoutId);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                btn.innerText = 'Initialize Expert System Analysis';
                btn.disabled = false;
                resultDiv.style.display = 'block';
                
                if(data.status === 'error') {
                    resultDiv.innerHTML = `<p style="color:red;">${data.message}</p>`;
                    return;
                }
                
                const results = data.data;
                if (results.length === 0) {
                    resultDiv.innerHTML = '<div class="center-align" style="padding: 3rem 1rem;">'
                        + '<i class="material-icons large grey-text text-lighten-2">psychology</i>'
                        + '<h5 class="grey-text">No Pending Projects</h5>'
                        + '<p class="grey-text">There are no pending projects to evaluate.</p>'
                        + '</div>';
                    return;
                }

                let html = '<table class="striped highlight responsive-table">';
                html += '<thead><tr>';
                html += '<th>ID</th><th>Title</th><th class="text-right">Budget (GHS)</th>';
                html += '<th class="text-right">Hours</th><th class="text-right">Student Reach</th>';
                html += '<th class="text-right">Weeks</th><th>Risk Assessment</th>';
                html += '</tr></thead><tbody>';

                results.forEach(res => {
                    html += '<tr>';
                    html += '<td>' + toSafeInt(res.project_id) + '</td>';
                    html += '<td>' + escapeHtml(res.title ?? '') + '</td>';
                    html += '<td class="text-right">' + toSafeFloat(res.budget_required, 2).toFixed(2) + '</td>';
                    html += '<td class="text-right">' + toSafeInt(res.volunteer_hours) + '</td>';
                    html += '<td class="text-right">' + toSafeInt(res.student_reach) + '</td>';
                    html += '<td class="text-right">' + toSafeInt(res.implementation_weeks) + '</td>';
                    html += '<td><ul style="margin:0;padding-left:1.25rem;font-size:0.875rem;">';
                    (res.advice || []).forEach(note => {
                        html += '<li style="margin-bottom:0.25rem;">' + escapeHtml(note) + '</li>';
                    });
                    html += '</ul></td>';
                    html += '</tr>';
                });

                html += '</tbody></table>';
                
                resultDiv.innerHTML = html;
            })
            .catch(err => {
                clearTimeout(timeoutId);
                btn.innerText = 'Initialize Expert System Analysis';
                btn.disabled = false;
                M.toast({html: 'Network or Server Error: Please check your connection or contact the System Administrator.', classes: 'red'});
            });
        });
    </script>
</body>
</html>
