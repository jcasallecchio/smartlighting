const LOGS_API = "./api/logs.php";
const REFRESH_INTERVAL = 3000;
const FILTER_VISIBILITY_KEY = "smartlighting.logs.filtersVisible";

const state = {
    paused: false,
    timer: null,
    logs: []
};

document.addEventListener("DOMContentLoaded", () => {
    restoreFilterVisibility();
    bindEvents();
    loadLogs();
    state.timer = setInterval(() => {
        if (!state.paused) {
            loadLogs();
        }
    }, REFRESH_INTERVAL);
});

function bindEvents() {
    document.getElementById("filterToggleButton").addEventListener("click", toggleFilters);
    document.getElementById("refreshButton").addEventListener("click", loadLogs);
    document.getElementById("pauseButton").addEventListener("click", togglePause);
    document.getElementById("exportButton").addEventListener("click", exportLogs);

    ["levelFilter", "categoryFilter", "dateFilter", "limitFilter"].forEach((id) => {
        document.getElementById(id).addEventListener("change", loadLogs);
    });

    let searchTimeout;
    document.getElementById("searchFilter").addEventListener("input", () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(loadLogs, 350);
    });
}

function restoreFilterVisibility() {
    const storedValue = localStorage.getItem(FILTER_VISIBILITY_KEY);
    setFiltersVisible(storedValue !== "false");
}

function toggleFilters() {
    const toolbar = document.getElementById("logToolbar");
    setFiltersVisible(toolbar.hidden);
}

function setFiltersVisible(isVisible) {
    const toolbar = document.getElementById("logToolbar");
    const button = document.getElementById("filterToggleButton");

    toolbar.hidden = !isVisible;
    button.setAttribute("aria-expanded", String(isVisible));
    button.textContent = isVisible ? "Ocultar busca" : "Exibir busca";
    localStorage.setItem(FILTER_VISIBILITY_KEY, String(isVisible));
}

async function loadLogs() {
    setConnectionState("loading", "Atualizando");

    try {
        const params = new URLSearchParams();
        const filters = {
            search: document.getElementById("searchFilter").value.trim(),
            level: document.getElementById("levelFilter").value,
            category: document.getElementById("categoryFilter").value,
            date: document.getElementById("dateFilter").value,
            limit: document.getElementById("limitFilter").value
        };

        Object.entries(filters).forEach(([key, value]) => {
            if (value) {
                params.set(key, value);
            }
        });

        const response = await fetch(`${LOGS_API}?${params.toString()}`, { cache: "no-store" });
        const result = await response.json();
        if (!response.ok || !result.success) {
            throw new Error(result.message || "Falha ao carregar os logs");
        }

        state.logs = result.data.logs;
        renderLogs(state.logs);
        renderStats(result.data);
        updateCategories(result.data.categories);
        setConnectionState("online", state.paused ? "Pausado" : "Ao vivo");
    } catch (error) {
        renderError(error.message);
        setConnectionState("offline", "Desconectado");
    }
}

function renderLogs(logs) {
    const output = document.getElementById("logOutput");
    output.replaceChildren();

    if (logs.length === 0) {
        const empty = document.createElement("p");
        empty.className = "empty-state";
        empty.textContent = "Nenhum evento encontrado para os filtros selecionados.";
        output.appendChild(empty);
        return;
    }

    const fragment = document.createDocumentFragment();
    logs.forEach((entry) => fragment.appendChild(createLogEntry(entry)));
    output.appendChild(fragment);
}

function createLogEntry(entry) {
    const row = document.createElement("article");
    const time = document.createElement("time");
    const level = document.createElement("span");
    const category = document.createElement("span");
    const message = document.createElement("span");
    const request = document.createElement("span");

    row.className = `log-entry level-${entry.level.toLowerCase()}`;
    time.className = "log-time";
    level.className = "log-level";
    category.className = "log-category";
    message.className = "log-message";
    request.className = "log-request";

    time.textContent = entry.timestamp;
    level.textContent = entry.level;
    category.textContent = `[${entry.category}]`;
    message.textContent = entry.message;
    request.textContent = entry.request_id ? `#${entry.request_id}` : "";

    row.append(time, level, category, message, request);

    if (entry.context && Object.keys(entry.context).length > 0) {
        const details = document.createElement("details");
        const summary = document.createElement("summary");
        const context = document.createElement("pre");
        details.className = "log-context";
        summary.textContent = "ver contexto";
        context.textContent = JSON.stringify(entry.context, null, 2);
        details.append(summary, context);
        row.appendChild(details);
    }

    return row;
}

function renderStats(data) {
    document.getElementById("totalCount").textContent = data.total;
    document.getElementById("errorCount").textContent = (data.levels.ERROR || 0) + (data.levels.CRITICAL || 0);
    document.getElementById("warningCount").textContent = data.levels.WARNING || 0;
    document.getElementById("successCount").textContent = data.levels.SUCCESS || 0;
    document.getElementById("lastUpdated").textContent = `Última leitura: ${new Date().toLocaleTimeString("pt-BR")}`;
}

function updateCategories(categories) {
    const select = document.getElementById("categoryFilter");
    const selected = select.value;
    const known = new Set(Array.from(select.options).map((option) => option.value));

    Object.keys(categories).forEach((category) => {
        if (!known.has(category)) {
            const option = document.createElement("option");
            option.value = category;
            option.textContent = category;
            select.appendChild(option);
        }
    });

    select.value = selected;
}

function togglePause() {
    state.paused = !state.paused;
    document.getElementById("pauseButton").textContent = state.paused ? "Retomar" : "Pausar";
    setConnectionState("online", state.paused ? "Pausado" : "Ao vivo");
}

function exportLogs() {
    const blob = new Blob([JSON.stringify(state.logs, null, 2)], { type: "application/json" });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = `smartlighting-logs-${new Date().toISOString().replace(/[:.]/g, "-")}.json`;
    link.click();
    URL.revokeObjectURL(link.href);
}

function renderError(message) {
    const output = document.getElementById("logOutput");
    const error = document.createElement("p");
    error.className = "empty-state";
    error.textContent = `Erro: ${message}`;
    output.replaceChildren(error);
}

function setConnectionState(status, label) {
    const element = document.getElementById("connectionState");
    element.className = `connection-state ${status}`;
    document.getElementById("connectionLabel").textContent = label;
}
