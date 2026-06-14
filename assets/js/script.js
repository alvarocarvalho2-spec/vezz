/**
 * VEZZ - Funções AJAX para atualização em tempo real
 */

const VEZZ = {
    baseUrl: '/VEZZ',
    intervaloAtualizacao: 5000,
    timerFilaGestor: null,
    timerFilaPaciente: null,
};

function badgeStatusClass(status) {
    const map = {
        'Agendada': 'badge-status badge-agendado',
        'Aguardando': 'badge-status badge-agendado',
        'Em Atendimento': 'badge-status badge-em-atendimento',
        'Finalizada': 'badge-status badge-finalizado',
        'Finalizado': 'badge-status badge-finalizado',
        'Cancelada': 'badge-status badge-cancelado',
        'Cancelado': 'badge-status badge-cancelado',
    };
    return map[status] || 'badge-status badge-cancelado';
}

function vezzAjax(url, options = {}) {
    return fetch(VEZZ.baseUrl + url, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.headers || {}),
        },
        credentials: 'same-origin',
        ...options,
    }).then(response => {
        if (!response.ok) {
            throw new Error('Erro na requisição');
        }
        return response.json();
    });
}

function atualizarDashboardFilaPaciente() {
    const container = document.getElementById('dashboard-fila-paciente');
    if (!container || !document.getElementById('dash-posicao')) return;

    vezzAjax('/api/fila_paciente.php')
        .then(data => {
            if (!data.tem_consulta) return;

            const posEl = document.getElementById('dash-posicao');
            const tempoEl = document.getElementById('dash-tempo');
            const statusEl = document.getElementById('dash-status');

            if (posEl) posEl.textContent = data.posicao || '-';
            if (tempoEl) tempoEl.textContent = data.tempo_estimado || '-';
            if (statusEl) statusEl.textContent = data.status;
        })
        .catch(err => console.error('Erro ao atualizar dashboard:', err));
}

function iniciarPollingDashboardPaciente() {
    if (document.getElementById('dashboard-fila-paciente')) {
        atualizarDashboardFilaPaciente();
        setInterval(atualizarDashboardFilaPaciente, VEZZ.intervaloAtualizacao);
    }
}

function atualizarFilaGestor() {
    const containerAguardando = document.getElementById('fila-aguardando');
    const containerAtual = document.getElementById('fila-atendimento-atual');
    const totalAguardando = document.getElementById('total-aguardando');

    if (!containerAguardando) return;

    vezzAjax('/api/fila_gestor.php')
        .then(data => {
            if (totalAguardando) {
                totalAguardando.textContent = data.total_aguardando;
            }

            if (data.aguardando.length === 0) {
                containerAguardando.innerHTML = '<p class="text-muted mb-0">Nenhum paciente aguardando.</p>';
            } else {
                let html = '<div class="list-group">';
                data.aguardando.forEach(item => {
                    html += `
                        <div class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <span class="badge badge-posicao me-2">#${item.posicao}</span>
                                <strong>${escapeHtml(item.nome_paciente)}</strong>
                                <br><small class="text-muted">${escapeHtml(item.data_hora)} | ${escapeHtml(item.telefone)}</small>
                            </div>
                            <form method="POST" action="controle_fila.php" class="d-inline">
                                <input type="hidden" name="acao" value="iniciar">
                                <input type="hidden" name="id_consulta" value="${item.id_consulta}">
                                <button type="submit" class="btn btn-success-custom btn-sm">
                                    <i class="fa-solid fa-play"></i> Iniciar Atendimento
                                </button>
                            </form>
                        </div>`;
                });
                html += '</div>';
                containerAguardando.innerHTML = html;
            }

            if (containerAtual) {
                if (data.atendimento_atual) {
                    const a = data.atendimento_atual;
                    containerAtual.innerHTML = `
                        <div class="queue-item em-atendimento">
                            <h5 class="text-success mb-2"><i class="fa-solid fa-user-doctor"></i> Em Atendimento</h5>
                            <p class="mb-1"><strong>${escapeHtml(a.nome_paciente)}</strong></p>
                            <p class="mb-2 text-muted">${escapeHtml(a.data_hora)} | ${escapeHtml(a.telefone)}</p>
                            <form method="POST" action="controle_fila.php" class="d-inline">
                                <input type="hidden" name="acao" value="finalizar">
                                <input type="hidden" name="id_consulta" value="${a.id_consulta}">
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <i class="fa-solid fa-circle-check"></i> Finalizar Atendimento
                                </button>
                            </form>
                        </div>`;
                } else {
                    containerAtual.innerHTML = '<p class="text-muted mb-0">Nenhum atendimento em andamento.</p>';
                }
            }
        })
        .catch(err => console.error('Erro ao atualizar fila:', err));
}

function atualizarPosicaoPaciente() {
    const container = document.getElementById('fila-paciente-dados');
    if (!container) return;

    vezzAjax('/api/fila_paciente.php')
        .then(data => {
            if (!data.tem_consulta) {
                container.innerHTML = `
                    <div class="alert alert-info">
                        <i class="fa-solid fa-circle-info"></i> ${escapeHtml(data.mensagem)}
                    </div>`;
                return;
            }

            const statusClass = badgeStatusClass(data.status);

            container.innerHTML = `
                <div class="text-center mb-4">
                    <p class="text-muted mb-1">${escapeHtml(data.nome_clinica)}</p>
                    <p class="mb-3">${escapeHtml(data.data_hora)}</p>
                    <span class="${statusClass} fs-6">${escapeHtml(data.status)}</span>
                </div>
                <div class="row text-center g-4">
                    <div class="col-md-4">
                        <div class="stat-card card card-custom h-100">
                            <div class="card-body">
                                <div class="queue-position">${data.posicao || '-'}</div>
                                <div class="stat-label">Sua Posição</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card card card-custom h-100">
                            <div class="card-body">
                                <div class="stat-number">${data.pacientes_a_frente}</div>
                                <div class="stat-label">Pacientes à Frente</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card card card-custom h-100">
                            <div class="card-body">
                                <div class="stat-number stat-number-sm">${escapeHtml(data.tempo_estimado)}</div>
                                <div class="stat-label">Tempo Estimado</div>
                            </div>
                        </div>
                    </div>
                </div>`;
        })
        .catch(err => console.error('Erro ao atualizar posição:', err));
}

function carregarHorariosDisponiveis() {
    const selectData = document.getElementById('data_consulta');
    const selectHorario = document.getElementById('horario_consulta');
    const idClinica = document.getElementById('id_clinica');
    const idConsulta = document.getElementById('id_consulta_reagendar');

    if (!selectData || !selectHorario || !idClinica) return;

    const data = selectData.value;
    if (!data) {
        selectHorario.innerHTML = '<option value="">Selecione a data primeiro</option>';
        selectHorario.disabled = true;
        return;
    }

    selectHorario.disabled = true;
    selectHorario.innerHTML = '<option value="">Carregando...</option>';

    let url = `/api/horarios_disponiveis.php?id_clinica=${idClinica.value}&data=${data}`;
    if (idConsulta && idConsulta.value) {
        url += `&id_consulta=${idConsulta.value}`;
    }

    vezzAjax(url)
        .then(horarios => {
            if (horarios && typeof horarios === 'object' && horarios.erro) {
                selectHorario.innerHTML = `<option value="">${escapeHtml(horarios.erro)}</option>`;
                return;
            }
            if (!Array.isArray(horarios)) {
                selectHorario.innerHTML = '<option value="">Erro ao carregar horários</option>';
                return;
            }
            if (horarios.length === 0) {
                selectHorario.innerHTML = '<option value="">Nenhum horário disponível</option>';
            } else {
                selectHorario.innerHTML = '<option value="">Selecione um horário</option>';
                horarios.forEach(h => {
                    selectHorario.innerHTML += `<option value="${h}">${h}</option>`;
                });
                selectHorario.disabled = false;
            }
        })
        .catch(() => {
            selectHorario.innerHTML = '<option value="">Erro ao carregar horários</option>';
        });
}

function iniciarPollingFilaGestor() {
    if (document.getElementById('fila-aguardando')) {
        atualizarFilaGestor();
        VEZZ.timerFilaGestor = setInterval(atualizarFilaGestor, VEZZ.intervaloAtualizacao);
    }
}

function iniciarPollingFilaPaciente() {
    if (document.getElementById('fila-paciente-dados')) {
        atualizarPosicaoPaciente();
        VEZZ.timerFilaPaciente = setInterval(atualizarPosicaoPaciente, VEZZ.intervaloAtualizacao);
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function mascaraCPF(input) {
    let v = input.value.replace(/\D/g, '');
    if (v.length > 11) v = v.slice(0, 11);
    v = v.replace(/(\d{3})(\d)/, '$1.$2');
    v = v.replace(/(\d{3})(\d)/, '$1.$2');
    v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    input.value = v;
}

function mascaraTelefone(input) {
    let v = input.value.replace(/\D/g, '');
    if (v.length > 11) v = v.slice(0, 11);
    if (v.length > 10) {
        v = v.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
    } else if (v.length > 6) {
        v = v.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
    } else if (v.length > 2) {
        v = v.replace(/(\d{2})(\d{0,5})/, '($1) $2');
    }
    input.value = v;
}

function mascaraCEP(input) {
    let v = input.value.replace(/\D/g, '');
    if (v.length > 8) v = v.slice(0, 8);
    v = v.replace(/(\d{5})(\d{1,3})/, '$1-$2');
    input.value = v;
}

function mascaraCNPJ(input) {
    let v = input.value.replace(/\D/g, '');
    if (v.length > 14) v = v.slice(0, 14);
    v = v.replace(/^(\d{2})(\d)/, '$1.$2');
    v = v.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
    v = v.replace(/\.(\d{3})(\d)/, '.$1/$2');
    v = v.replace(/(\d{4})(\d)/, '$1-$2');
    input.value = v;
}

document.addEventListener('DOMContentLoaded', function () {
    iniciarPollingFilaGestor();
    iniciarPollingFilaPaciente();
    iniciarPollingDashboardPaciente();

    const selectData = document.getElementById('data_consulta');
    if (selectData) {
        selectData.addEventListener('change', carregarHorariosDisponiveis);
        if (selectData.value) {
            carregarHorariosDisponiveis();
        }
    }

    document.querySelectorAll('[data-mask="cpf"]').forEach(el => {
        el.addEventListener('input', () => mascaraCPF(el));
    });

    document.querySelectorAll('[data-mask="telefone"]').forEach(el => {
        el.addEventListener('input', () => mascaraTelefone(el));
    });

    document.querySelectorAll('[data-mask="cep"]').forEach(el => {
        el.addEventListener('input', () => mascaraCEP(el));
    });

    document.querySelectorAll('[data-mask="cnpj"]').forEach(el => {
        el.addEventListener('input', () => mascaraCNPJ(el));
    });
});
