const API_BASE = "./api";
const STATUS_REFRESH_INTERVAL = 5000;
const TOAST_DURATION = 2250;
const TIMER_STORAGE_KEY = "smartlighting.timer.config";

// This order must match COLOR_SEQUENCE in .env.
const COLORS = [
    { key: "colorido", name: "Colorido", value: "conic-gradient(#f44336, #ff9800, #ffeb3b, #4caf50, #03a9f4, #673ab7, #f44336)" },
    { key: "rosa", name: "Rosa", value: "#ff4fa3" },
    { key: "azul", name: "Azul", value: "#2878ff" },
    { key: "vermelho", name: "Vermelho", value: "#ef3340" },
    { key: "verde", name: "Verde", value: "#35c759" },
    { key: "ciano", name: "Ciano", value: "#1ed6d1" },
    { key: "roxo", name: "Roxo", value: "#8b3dff" },
    { key: "amarelo", name: "Amarelo", value: "#ffd60a" },
    { key: "branco", name: "Branco", value: "#ffffff" }
];

const appState = {
    deviceState: null,
    currentColor: null,
    targetColor: "colorido",
    busy: false,
    activePanel: null,
    savedTimerConfig: null,
    toastTimer: null,
    toastCleanupTimer: null
};

document.addEventListener("DOMContentLoaded", () => {
    buildColorControls();
    populateTimerColors();
    restoreTimerConfig();
    bindEvents();
    syncDeviceState();
    window.setInterval(syncDeviceState, STATUS_REFRESH_INTERVAL);
});

function bindEvents() {
    document.getElementById("powerButton").addEventListener("click", togglePower);
    document.getElementById("colorsButton").addEventListener("click", () => togglePanel("colors"));
    document.getElementById("timerButton").addEventListener("click", () => togglePanel("timer"));
    document.getElementById("confirmColorsButton").addEventListener("click", applySelectedColor);
    document.getElementById("confirmTimerButton").addEventListener("click", submitTimer);
    document.getElementById("turnOnTimerEnabled").addEventListener("change", updateTimerFields);
    document.getElementById("turnOffTimerEnabled").addEventListener("change", updateTimerFields);
}

async function apiCall(endpoint, method = "GET", data = null) {
    const options = {
        method,
        headers: { "Content-Type": "application/json" },
        cache: "no-store"
    };

    if (data !== null) {
        options.body = JSON.stringify(data);
    }

    const response = await fetch(`${API_BASE}${endpoint}`, options);
    const result = await response.json();

    if (!response.ok || !result.success) {
        throw new Error(result.message || "N\u00e3o foi poss\u00edvel concluir a opera\u00e7\u00e3o");
    }

    return result;
}

async function syncDeviceState() {
    if (appState.busy) {
        return;
    }

    try {
        const result = await apiCall("/state.php");
        appState.deviceState = result.data.state;

        if (appState.deviceState === "off") {
            appState.currentColor = null;
        }

        updatePowerInterface();
        updateCurrentColorInterface();
        document.getElementById("lastSync").textContent = `Sincronizado \u00e0s ${new Date().toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" })}`;
    } catch (error) {
        appState.deviceState = null;
        updatePowerInterface();
        updateCurrentColorInterface();
        document.getElementById("lastSync").textContent = "Falha na sincroniza\u00e7\u00e3o";
        showMessage(error.message, "error");
    }
}

async function togglePower() {
    if (appState.busy || appState.deviceState === null) {
        return;
    }

    const shouldTurnOn = appState.deviceState !== "on";
    setBusy(true, shouldTurnOn ? "Ligando..." : "Desligando...");

    try {
        if (shouldTurnOn) {
            await apiCall("/on.php", "POST", {});
            appState.deviceState = "on";
            appState.currentColor = "colorido";
            showMessage("Dispositivo ligado", "success");
        } else {
            await apiCall("/off.php", "POST", {});
            appState.deviceState = "off";
            appState.currentColor = null;
            showMessage("Dispositivo desligado", "success");
        }
    } catch (error) {
        showMessage(error.message, "error");
    } finally {
        setBusy(false);
        updateCurrentColorInterface();
        await syncDeviceState();
    }
}

function setBusy(isBusy, label = "") {
    appState.busy = isBusy;
    document.getElementById("powerButton").disabled = isBusy || appState.deviceState === null;
    document.getElementById("confirmColorsButton").disabled = isBusy;
    document.getElementById("confirmTimerButton").disabled = isBusy;

    if (isBusy) {
        document.getElementById("powerButtonLabel").textContent = label;
        document.getElementById("powerButtonHint").textContent = "Aguardando Home Assistant";
    } else {
        updatePowerInterface();
    }
}

function updatePowerInterface() {
    const button = document.getElementById("powerButton");
    const isOn = appState.deviceState === "on";
    const isUnknown = appState.deviceState === null;

    button.disabled = appState.busy || isUnknown;
    button.classList.toggle("is-on", isOn);
    document.getElementById("powerButtonLabel").textContent = isOn ? "Desligar" : "Ligar";
    document.getElementById("powerButtonHint").textContent = isUnknown
        ? "Status indispon\u00edvel"
        : isOn ? "Dispositivo ligado" : "Dispositivo desligado";
}

function togglePanel(panelName) {
    const nextPanel = appState.activePanel === panelName ? null : panelName;
    appState.activePanel = nextPanel;

    ["colors", "timer"].forEach((name) => {
        const isOpen = name === nextPanel;
        document.getElementById(`${name}Panel`).hidden = !isOpen;
        document.getElementById(`${name}Button`).setAttribute("aria-expanded", String(isOpen));
    });

    if (nextPanel) {
        window.requestAnimationFrame(() => {
            document.getElementById(`${nextPanel}Panel`).scrollIntoView({ behavior: "smooth", block: "nearest" });
        });
    }
}

function buildColorControls() {
    const currentGrid = document.getElementById("currentColorGrid");
    const targetGrid = document.getElementById("targetColorGrid");

    COLORS.forEach((color) => {
        currentGrid.appendChild(createColorButton(color, "current"));
        targetGrid.appendChild(createColorButton(color, "target"));
    });

    updateCurrentColorInterface();
    updateTargetColorInterface();
}

function createColorButton(color, group) {
    const button = document.createElement("button");
    const swatch = document.createElement("span");
    const label = document.createElement("span");

    button.type = "button";
    button.className = "color-option";
    button.dataset.color = color.key;
    button.setAttribute("aria-label", `${group === "current" ? "Cor atual" : "Selecionar"}: ${color.name}`);
    swatch.className = "color-swatch";
    swatch.style.background = color.value;
    label.textContent = color.name;
    button.append(swatch, label);

    button.addEventListener("click", () => {
        if (group === "current") {
            if (appState.deviceState !== "on") return;
            appState.currentColor = color.key;
            updateCurrentColorInterface();
        } else {
            appState.targetColor = color.key;
            updateTargetColorInterface();
        }
    });

    return button;
}

function updateCurrentColorInterface() {
    const isAvailable = appState.deviceState === "on";
    const card = document.getElementById("currentColorCard");
    card.classList.toggle("is-disabled", !isAvailable);
    card.setAttribute("aria-disabled", String(!isAvailable));
    document.getElementById("currentColorName").textContent = !isAvailable
        ? "Indispon\u00edvel"
        : appState.currentColor ? getColor(appState.currentColor).name : "Selecione";

    document.querySelectorAll("#currentColorGrid .color-option").forEach((button) => {
        button.disabled = !isAvailable;
        const selected = isAvailable && button.dataset.color === appState.currentColor;
        button.classList.toggle("is-selected", selected);
        button.setAttribute("aria-pressed", String(selected));
    });
}

function updateTargetColorInterface() {
    const color = getColor(appState.targetColor);
    document.getElementById("targetColorName").textContent = color.name;
    document.querySelectorAll("#targetColorGrid .color-option").forEach((button) => {
        const selected = button.dataset.color === color.key;
        button.classList.toggle("is-selected", selected);
        button.setAttribute("aria-pressed", String(selected));
    });
}

async function applySelectedColor() {
    if (appState.deviceState === "on" && !appState.currentColor) {
        showMessage("Informe a cor atual da lumin\u00e1ria.", "error");
        return;
    }

    const target = getColor(appState.targetColor);
    setBusy(true, "Processando...");
    document.getElementById("confirmColorsButton").textContent = "Aplicando...";

    try {
        const result = await apiCall("/color_apply.php", "POST", {
            current_color: appState.deviceState === "on" ? appState.currentColor : null,
            target_color: appState.targetColor
        });
        appState.deviceState = "on";
        appState.currentColor = appState.targetColor;
        updateCurrentColorInterface();
        showMessage(`${target.name} aplicada em ${result.data.cycles} ciclo(s).`, "success");
    } catch (error) {
        showMessage(error.message, "error");
    } finally {
        document.getElementById("confirmColorsButton").textContent = "Confirmar";
        setBusy(false);
        await syncDeviceState();
    }
}

function populateTimerColors() {
    const select = document.getElementById("turnOnColor");
    COLORS.forEach((color) => {
        const option = document.createElement("option");
        option.value = color.key;
        option.textContent = color.name;
        select.appendChild(option);
    });
}

function updateTimerFields() {
    const turnOnEnabled = document.getElementById("turnOnTimerEnabled").checked;
    const turnOffEnabled = document.getElementById("turnOffTimerEnabled").checked;
    document.getElementById("turnOnTime").disabled = !turnOnEnabled;
    document.getElementById("turnOnColor").disabled = !turnOnEnabled;
    document.getElementById("turnOffTime").disabled = !turnOffEnabled;
}

function readTimerForm() {
    return {
        turnOnEnabled: document.getElementById("turnOnTimerEnabled").checked,
        turnOnTime: document.getElementById("turnOnTime").value,
        turnOnColor: document.getElementById("turnOnColor").value || "colorido",
        turnOffEnabled: document.getElementById("turnOffTimerEnabled").checked,
        turnOffTime: document.getElementById("turnOffTime").value
    };
}

function restoreTimerConfig() {
    let config = null;

    try {
        config = JSON.parse(window.localStorage.getItem(TIMER_STORAGE_KEY));
    } catch (error) {
        config = null;
    }

    if (!config || typeof config !== "object") {
        config = {
            turnOnEnabled: false,
            turnOnTime: "",
            turnOnColor: "colorido",
            turnOffEnabled: false,
            turnOffTime: ""
        };
    }

    document.getElementById("turnOnTimerEnabled").checked = Boolean(config.turnOnEnabled);
    document.getElementById("turnOnTime").value = config.turnOnTime || "";
    document.getElementById("turnOnColor").value = getColor(config.turnOnColor).key;
    document.getElementById("turnOffTimerEnabled").checked = Boolean(config.turnOffEnabled);
    document.getElementById("turnOffTime").value = config.turnOffTime || "";
    appState.savedTimerConfig = { ...config };
    updateTimerFields();
}

function saveTimerConfig(config) {
    try {
        window.localStorage.setItem(TIMER_STORAGE_KEY, JSON.stringify(config));
    } catch (error) {
        // Keep the confirmed state in memory when browser storage is unavailable.
    }
    appState.savedTimerConfig = { ...config };
}

async function submitTimer() {
    const config = readTimerForm();
    const previous = appState.savedTimerConfig || {};
    const actions = [];

    if (config.turnOnEnabled) {
        if (!config.turnOnTime) {
            showMessage("Informe o hor\u00e1rio para ligar.", "error");
            return;
        }
        actions.push({ type: "on", time: config.turnOnTime, color: config.turnOnColor });
    } else if (previous.turnOnEnabled) {
        actions.push({ type: "cancel_on" });
    }

    if (config.turnOffEnabled) {
        if (!config.turnOffTime) {
            showMessage("Informe o hor\u00e1rio para desligar.", "error");
            return;
        }
        actions.push({ type: "off", time: config.turnOffTime });
    } else if (previous.turnOffEnabled) {
        actions.push({ type: "cancel_off" });
    }

    if (actions.length === 0) {
        showMessage("Nenhuma altera\u00e7\u00e3o para enviar.", "error");
        return;
    }

    setBusy(true, appState.deviceState === "on" ? "Desligar" : "Ligar");
    document.getElementById("confirmTimerButton").textContent = "Enviando...";

    try {
        await apiCall("/timer_submit.php", "POST", { actions });
        saveTimerConfig(config);
        showMessage("Programa\u00e7\u00e3o enviada com sucesso.", "success");
    } catch (error) {
        showMessage(error.message, "error");
    } finally {
        document.getElementById("confirmTimerButton").textContent = "Confirmar";
        setBusy(false);
    }
}

function getColor(colorKey) {
    return COLORS.find((color) => color.key === colorKey) || COLORS[0];
}

function showMessage(text, type) {
    const toast = document.getElementById("toast");
    const message = document.getElementById("toastMessage");
    const icon = document.getElementById("toastIcon");
    const progress = toast.querySelector(".toast-progress");

    window.clearTimeout(appState.toastTimer);
    window.clearTimeout(appState.toastCleanupTimer);
    toast.hidden = false;
    toast.className = "toast";
    message.textContent = text;
    icon.textContent = type === "error" ? "!" : "\u2713";
    progress.style.animation = "none";
    void toast.offsetWidth;
    progress.style.animation = "";
    toast.className = `toast ${type}`;

    appState.toastTimer = window.setTimeout(() => {
        toast.classList.add("is-leaving");
        appState.toastCleanupTimer = window.setTimeout(() => {
            toast.hidden = true;
            toast.className = "toast";
            message.textContent = "";
        }, 220);
    }, TOAST_DURATION);
}
