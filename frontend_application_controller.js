/**
 * Frontend State Manager & Controller File
 * Plain modern ES6 JavaScript communicating directly with our PDO backend APIs
 */

// --- Global Application State Container ---
const state = {
  currentRole: 'employee',      // 'employee' | 'driver'
  activeEmployeeTab: 'assign',  // 'assign' | 'register' | 'monitor'
  activeDriverTab: 'tasks',     // 'tasks' | 'map' | 'fuel'
  tasks: [],
  drivers: [],
  vehicles: [],
  currentDriverId: 'alex',      // Simulated Driver
  activeNavigationTask: null,   // Currently navigated route
  unlockedAddresses: {}         // Stores decrypted addresses indexable by task_id
};

// --- DOM Initializers ---
document.addEventListener('DOMContentLoaded', () => {
  initRoleButtons();
  initTabButtons();
  initFormTypeSelectors();
  initFormSubmitHandlers();
  
  // Trigger initial background poll fetch cycles
  pollDatabaseState();
});

// --- Role Switcher Action Handler ---
function initRoleButtons() {
  const empBtn = document.getElementById('role-btn-employee');
  const drvBtn = document.getElementById('role-btn-driver');
  const empView = document.getElementById('view-employee');
  const drvView = document.getElementById('view-driver');

  empBtn.addEventListener('click', () => {
    state.currentRole = 'employee';
    empBtn.className = "px-5 py-2 rounded-lg text-sm font-bold transition-all bg-indigo-600 text-white shadow-md";
    drvBtn.className = "px-5 py-2 rounded-lg text-sm font-bold transition-all text-slate-400 hover:text-white";
    empView.classList.remove('hidden');
    drvView.classList.add('hidden');
    pollDatabaseState();
  });

  drvBtn.addEventListener('click', () => {
    state.currentRole = 'driver';
    drvBtn.className = "px-5 py-2 rounded-lg text-sm font-bold transition-all bg-emerald-600 text-white shadow-md";
    empBtn.className = "px-5 py-2 rounded-lg text-sm font-bold transition-all text-slate-400 hover:text-white";
    drvView.classList.remove('hidden');
    empView.classList.add('hidden');
    pollDatabaseState();
  });
}

// --- Tab Toggles ---
function initTabButtons() {
  // Employee Sub-Tabs
  const tabs = {
    assign: { btn: 'tab-btn-assign', view: 'subview-assign' },
    register: { btn: 'tab-btn-register', view: 'subview-register' },
    monitor: { btn: 'tab-btn-monitor', view: 'subview-monitor' }
  };

  Object.keys(tabs).forEach(tabKey => {
    document.getElementById(tabs[tabKey].btn).addEventListener('click', () => {
      state.activeEmployeeTab = tabKey;
      Object.keys(tabs).forEach(k => {
        const targetBtn = document.getElementById(tabs[k].btn);
        const targetView = document.getElementById(tabs[k].view);
        if (k === tabKey) {
          targetBtn.className = "flex-1 py-2 text-xs font-bold rounded-lg bg-indigo-600 text-white shadow-sm transition-all";
          targetView.classList.remove('hidden');
        } else {
          targetBtn.className = "flex-1 py-2 text-xs font-bold rounded-lg bg-slate-200/70 text-slate-600 hover:bg-slate-200 transition-all";
          targetView.classList.add('hidden');
        }
      });
    });
  });

  // Driver Navigation Sub-Tabs
  const drvTabs = {
    tasks: { btn: 'nav-btn-tasks', view: 'driver-subview-tasks' },
    map: { btn: 'nav-btn-map', view: 'driver-subview-map' },
    fuel: { btn: 'nav-btn-fuel', view: 'driver-subview-fuel' }
  };

  Object.keys(drvTabs).forEach(tabKey => {
    document.getElementById(drvTabs[tabKey].btn).addEventListener('click', () => {
      state.activeDriverTab = tabKey;
      Object.keys(drvTabs).forEach(k => {
        const btn = document.getElementById(drvTabs[k].btn);
        const view = document.getElementById(drvTabs[k].view);
        if (k === tabKey) {
          btn.className = "flex flex-col items-center text-blue-600";
          view.classList.remove('hidden');
        } else {
          btn.className = "flex flex-col items-center text-slate-400";
          view.classList.add('hidden');
        }
      });
      if (tabKey === 'map') {
        renderMapTelemetry();
      }
    });
  });
}

// --- Task Type Selectors (Delivery / Collection) ---
function initFormTypeSelectors() {
  const delBtn = document.getElementById('form-type-delivery');
  const colBtn = document.getElementById('form-type-collection');
  const input = document.getElementById('task-type-input');

  delBtn.addEventListener('click', () => {
    input.value = 'delivery';
    delBtn.className = "flex-1 py-1.5 text-xs font-bold rounded-lg bg-white text-indigo-700 shadow-sm";
    colBtn.className = "flex-1 py-1.5 text-xs font-bold rounded-lg text-slate-500";
  });

  colBtn.addEventListener('click', () => {
    input.value = 'collection';
    colBtn.className = "flex-1 py-1.5 text-xs font-bold rounded-lg bg-white text-indigo-700 shadow-sm";
    delBtn.className = "flex-1 py-1.5 text-xs font-bold rounded-lg text-slate-500";
  });
}

// --- API Form Submit Triggers ---
function initFormSubmitHandlers() {
  // 1. Employee creates a task and directly dispatches
  document.getElementById('create-task-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    const taskPayload = {
      type: document.getElementById('task-type-input').value,
      customer: form.customer.value,
      area: form.area.value,
      exact_address: form.exact_address.value
    };

    try {
      // Step A: Add pending task to database
      const addRes = await fetchApi('api/add_task.php', 'POST', taskPayload);
      if (addRes.status === 'success') {
        // Step B: Directly assign selected driver & vehicle, bypassing moderator workflow
        const assignPayload = {
          task_id: addRes.data.id,
          driver_id: form.driver_id.value,
          vehicle_id: form.vehicle_id.value
        };
        const assignRes = await fetchApi('api/assign_task.php', 'POST', assignPayload);
        if (assignRes.status === 'success') {
          triggerToast(`Successfully assigned directly to Driver! Route Code PIN: ${assignRes.data.pincode}`);
          form.reset();
          // Reset task type toggle buttons to default
          document.getElementById('form-type-delivery').click();
          pollDatabaseState();
        }
      }
    } catch (err) {
      triggerToast("Failed to create dispatch route link", true);
    }
  });

  // 2. Driver PIN address decrypter code verification
  document.getElementById('pincode-unlock-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const taskId = document.getElementById('pincode-task-id').value;
    const pincode = e.target.pincode.value;

    try {
      const res = await fetchApi('api/verify_pin.php', 'POST', { task_id: taskId, pincode });
      if (res.status === 'success') {
        state.unlockedAddresses[taskId] = res.data.exact_address;
        document.getElementById('pin-modal-overlay').classList.add('hidden');
        e.target.reset();
        triggerToast("Route unlocked. Destination address decrypted.");
        renderDriverTasks();
      }
    } catch (err) {
      triggerToast("Incorrect verification code PIN", true);
    }
  });

  document.getElementById('pin-cancel-btn').addEventListener('click', () => {
    document.getElementById('pin-modal-overlay').classList.add('hidden');
  });

  // 3. Fuel Voucher Request Handler
  document.getElementById('fuel-request-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const driverActiveVehicle = state.vehicles.find(v => v.status === 'In Use') || state.vehicles[0];
    if (!driverActiveVehicle) {
      triggerToast("No active vehicle detected", true);
      return;
    }

    const payload = {
      amount: e.target.amount.value,
      station: e.target.station.value,
      vehicle_id: driverActiveVehicle.id
    };

    try {
      const res = await fetchApi('api/request_fuel.php', 'POST', payload);
      if (res.status === 'success') {
        triggerToast("Fuel voucher requested & approved!");
        e.target.reset();
        pollDatabaseState();
      }
    } catch (err) {
      triggerToast("Voucher processing error", true);
    }
  });
}

// --- Global API Network Client Module ---
async function fetchApi(url, method = 'GET', body = null) {
  const options = {
    method,
    headers: { 'Content-Type': 'application/json' }
  };
  if (body) {
    options.body = JSON.stringify(body);
  }
  
  const response = await fetch(url, options);
  const data = await response.json();
  if (!response.ok || data.status === 'error') {
    throw new Error(data.message || 'API Error Occurred');
  }
  return data;
}

// --- Data Synchronizer Sync Cycles ---
async function pollDatabaseState() {
  try {
    const [tasksRes, fleetRes] = await Promise.all([
      fetchApi('api/get_tasks.php'),
      fetchApi('api/get_fleet.php')
    ]);

    state.tasks = tasksRes.data;
    state.drivers = fleetRes.data.drivers;
    state.vehicles = fleetRes.data.vehicles;

    updateDOMControllers();
  } catch (err) {
    console.error("Critical poll cycle failed to synchronize payload:", err);
  }
}

// --- DOM Painters ---
function updateDOMControllers() {
  populateDropdownSelectors();
  renderLiveRegister();
  renderEmployeeMonitor();
  renderDriverProfile();
  renderDriverTasks();
}

function populateDropdownSelectors() {
  const drvSelect = document.getElementById('driver-select');
  const vehSelect = document.getElementById('vehicle-select');
  
  const availableDrivers = state.drivers.filter(d => d.status === 'Available');
  const availableVehicles = state.vehicles.filter(v => v.status === 'Available');

  drvSelect.innerHTML = availableDrivers.length 
    ? availableDrivers.map(d => `<option value="${d.id}">${d.name}</option>`).join('')
    : `<option value="">No drivers available</option>`;

  vehSelect.innerHTML = availableVehicles.length
    ? availableVehicles.map(v => `<option value="${v.id}">${v.plate} (${v.model}) - Fuel: ${v.fuel}%</option>`).join('')
    : `<option value="">No vehicles available</option>`;
}

function renderLiveRegister() {
  const drvContainer = document.getElementById('drivers-list-container');
  const vehContainer = document.getElementById('vehicles-list-container');

  drvContainer.innerHTML = state.drivers.map(d => `
    <div class="bg-white p-3 rounded-xl border border-slate-100 flex items-center justify-between shadow-sm">
      <div class="flex items-center space-x-3">
        <img src="${d.avatar}" class="w-8 h-8 rounded-full bg-slate-100" alt="Avatar">
        <div>
          <div class="font-bold text-slate-800 text-sm">${d.name}</div>
          <div class="text-[10px] text-slate-400">ID: ${d.id.toUpperCase()}</div>
        </div>
      </div>
      <span class="text-[10px] px-2.5 py-0.5 font-bold rounded-full ${d.status === 'Available' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}">${d.status}</span>
    </div>
  `).join('');

  vehContainer.innerHTML = state.vehicles.map(v => `
    <div class="bg-white p-3 rounded-xl border border-slate-100 shadow-sm flex justify-between items-center">
      <div class="flex items-center space-x-3">
        <div class="p-2 rounded-lg ${v.status === 'Available' ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500'}">
          <svg class="w-5.5 h-5.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125a1.125 1.125 0 001.125-1.125V9.75M3.75 12h16.5M12 15.75V12m-6.75 0h13.5M3 9.75l1.658-3.316a3 3 0 012.683-1.684h10.318a3 3 0 012.683 1.684L21 9.75"/></svg>
        </div>
        <div>
          <div class="font-bold text-slate-800 text-sm">${v.plate}</div>
          <div class="text-[10px] text-slate-500 font-mono">${v.model}</div>
        </div>
      </div>
      <div class="text-right">
        <span class="text-[10px] font-bold px-2 py-0.5 rounded ${v.status === 'Available' ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700'}">${v.status}</span>
        <div class="text-[10px] text-slate-400 font-semibold mt-1">Fuel: ${v.fuel}%</div>
      </div>
    </div>
  `).join('');
}

function renderEmployeeMonitor() {
  const container = document.getElementById('active-tasks-container');
  container.innerHTML = state.tasks.map(t => `
    <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-100 space-y-3 relative overflow-hidden">
      <div class="absolute top-0 left-0 w-1.5 h-full ${t.type === 'delivery' ? 'bg-indigo-600' : 'bg-purple-600'}"></div>
      <div class="flex justify-between items-start pl-2">
        <div>
          <div class="text-[10px] font-mono text-slate-400 font-bold">${t.id} • ${t.type.toUpperCase()}</div>
          <h4 class="font-bold text-sm text-slate-800 mt-0.5">${t.customer}</h4>
        </div>
        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full ${t.status === 'completed' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}">${t.status.replace('_', ' ')}</span>
      </div>
      <div class="grid grid-cols-2 gap-2 text-xs bg-slate-50 p-2 rounded-lg pl-3">
        <div>
          <span class="text-slate-400 font-semibold block text-[10px] uppercase">Assigned Driver</span>
          <span class="text-slate-700 font-bold">${t.driver_name || 'Unassigned'}</span>
        </div>
        <div>
          <span class="text-slate-400 font-semibold block text-[10px] uppercase">Route Key PIN</span>
          <span class="text-indigo-700 font-mono font-bold tracking-wider">${t.pincode || '----'}</span>
        </div>
      </div>
    </div>
  `).join('');
}

function renderDriverProfile() {
  const driverObj = state.drivers.find(d => d.id === state.currentDriverId);
  const activeTask = state.tasks.find(t => t.driver_id === state.currentDriverId && t.status === 'in_transit');
  
  if (driverObj) {
    document.getElementById('driver-profile-name').textContent = driverObj.name;
    document.getElementById('driver-profile-avatar').src = driverObj.avatar;
    
    // Dynamically query vehicle assigned to driver
    const boundVehicle = state.vehicles.find(v => v.id === 'V-3456'); // alex default static mock binding
    if (boundVehicle) {
      document.getElementById('driver-profile-fuel').textContent = `${boundVehicle.fuel}%`;
      document.getElementById('fuel-tank-display').textContent = `${boundVehicle.fuel}%`;
      document.getElementById('fuel-progress-bar').style.width = `${boundVehicle.fuel}%`;
    }
  }
}

function renderDriverTasks() {
  const container = document.getElementById('driver-tasks-container');
  const driverRuns = state.tasks.filter(t => t.driver_id === state.currentDriverId);

  container.innerHTML = driverRuns.map(t => {
    const isUnlocked = state.unlockedAddresses[t.id] !== undefined || t.status === 'completed';
    const unlockedAddress = state.unlockedAddresses[t.id] || t.exact_address;

    return `
      <div class="bg-white rounded-2xl p-4 border shadow-sm ${t.status === 'in_transit' ? 'border-indigo-400' : 'border-slate-100'} transition-all space-y-3">
        <div class="flex justify-between items-start">
          <div class="flex items-center space-x-3">
            <div class="p-2.5 rounded-xl bg-slate-50 text-slate-600">
              ${t.type === 'delivery' 
                ? `<svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125a1.125 1.125 0 001.125-1.125V9.75M3.75 12h16.5M12 15.75V12m-6.75 0h13.5M3 9.75l1.658-3.316a3 3 0 012.683-1.684h10.318a3 3 0 012.683 1.684L21 9.75"/></svg>`
                : `<svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/></svg>`
              }
            </div>
            <div>
              <h4 class="font-bold text-sm text-slate-800 leading-tight">${t.customer}</h4>
              <span class="text-[10px] font-mono text-slate-400">${t.id} • ${t.type.toUpperCase()}</span>
            </div>
          </div>
          <span class="text-[9px] uppercase font-bold tracking-wider px-2 py-0.5 rounded bg-slate-100 text-slate-600">${t.status.replace('_', ' ')}</span>
        </div>

        <div class="text-xs space-y-1">
          <div class="flex items-start">
            <svg class="w-4 h-4 text-indigo-600 mr-1.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25s-7.5-4.108-7.5-11.25a7.5 7.5 0 1115 0z"/></svg>
            <div>
              <span class="font-bold text-slate-700">${t.area}</span>
              ${isUnlocked 
                ? `<p class="text-slate-500 mt-0.5 text-[11px]">${unlockedAddress}</p>`
                : `<div class="mt-1 text-[11px] text-amber-600 font-semibold flex items-center space-x-1">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25z"/></svg>
                    <span>Exact address locked</span>
                   </div>`
              }
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="pt-2 border-t border-slate-50 flex gap-2">
          ${!isUnlocked && t.status !== 'completed'
            ? `<button onclick="openUnlockModal('${t.id}')" class="w-full bg-slate-900 text-white py-2.5 rounded-xl text-xs font-bold flex items-center justify-center space-x-1">
                 <span>Unlock exact route destination</span>
               </button>`
            : ''
          }
          ${isUnlocked && t.status === 'assigned'
            ? `<button onclick="engageNavigation('${t.id}')" class="w-full bg-indigo-600 text-white py-2.5 rounded-xl text-xs font-bold flex items-center justify-center space-x-1">
                 <span>Engage navigation telemetry</span>
               </button>`
            : ''
          }
          ${t.status === 'in_transit'
            ? `<button onclick="switchToMapTab()" class="flex-1 bg-indigo-50 text-indigo-700 py-2.5 rounded-xl text-xs font-bold">GPS View</button>
               <button onclick="completeCurrentRoute('${t.id}')" class="flex-1 bg-emerald-500 text-white py-2.5 rounded-xl text-xs font-bold">Complete Route</button>`
            : ''
          }
        </div>
      </div>
    `;
  }).join('');
}

// --- Interface Overlay Openers ---
window.openUnlockModal = function(taskId) {
  document.getElementById('pincode-task-id').value = taskId;
  document.getElementById('pin-modal-overlay').classList.remove('hidden');
};

window.engageNavigation = async function(taskId) {
  try {
    const res = await fetchApi('api/update_task_status.php', 'POST', { task_id: taskId, status: 'in_transit' });
    if (res.status === 'success') {
      const activeTask = state.tasks.find(t => t.id === taskId);
      state.activeNavigationTask = activeTask;
      switchToMapTab();
      pollDatabaseState();
    }
  } catch (err) {
    triggerToast("Failed to start navigation", true);
  }
};

window.switchToMapTab = function() {
  document.getElementById('nav-btn-map').click();
};

window.completeCurrentRoute = async function(taskId) {
  try {
    const res = await fetchApi('api/update_task_status.php', 'POST', { task_id: taskId, status: 'completed' });
    if (res.status === 'success') {
      triggerToast("Route complete! Driver and vehicle released to Available.");
      state.activeNavigationTask = null;
      document.getElementById('nav-btn-tasks').click();
      pollDatabaseState();
    }
  } catch (err) {
    triggerToast("Failed to complete route", true);
  }
};

// --- Telemetry Simulation engine ---
let gpsInterval = null;
function renderMapTelemetry() {
  const activeTask = state.tasks.find(t => t.driver_id === state.currentDriverId && t.status === 'in_transit');
  const mapDashed = document.getElementById('map-dashed-path');
  const mapProgress = document.getElementById('map-active-progress');
  const mapVehicle = document.getElementById('map-vehicle-node');
  const mapLabel = document.getElementById('map-node-label');
  const addressDisplay = document.getElementById('gps-address-display');
  const percentText = document.getElementById('gps-percent-text');
  const statusText = document.getElementById('gps-status-text');
  const loadingBar = document.getElementById('gps-loading-bar');
  const completionBtn = document.getElementById('gps-completion-btn');

  if (gpsInterval) clearInterval(gpsInterval);

  if (!activeTask) {
    addressDisplay.textContent = "No active routes in progress";
    percentText.textContent = "0% completed";
    statusText.textContent = "Standby";
    loadingBar.style.width = "0%";
    completionBtn.classList.add('hidden');
    return;
  }

  addressDisplay.textContent = state.unlockedAddresses[activeTask.id] || activeTask.exact_address;
  mapLabel.textContent = activeTask.customer;
  completionBtn.classList.add('hidden');

  let pct = 0;
  const startX = 80, startY = 380;
  const endX = 260, endY = 140;

  gpsInterval = setInterval(() => {
    pct += 5;
    if (pct >= 100) {
      pct = 100;
      clearInterval(gpsInterval);
      completionBtn.classList.remove('hidden');
      completionBtn.onclick = () => completeCurrentRoute(activeTask.id);
      statusText.textContent = "Arrived at destination";
    } else {
      statusText.textContent = "Driving to target node...";
    }

    // Move SVG vehicle coordinate elements
    const currentX = startX + ((endX - startX) * (pct / 100));
    const currentY = startY + ((endY - startY) * (pct / 100));

    mapVehicle.style.left = `${currentX}px`;
    mapVehicle.style.top = `${currentY}px`;

    mapProgress.setAttribute('x2', currentX);
    mapProgress.setAttribute('y2', currentY);

    percentText.textContent = `${pct}% completed`;
    loadingBar.style.width = `${pct}%`;
  }, 500);
}

// --- Status Banner Helper ---
function triggerToast(message, isError = false) {
  const container = document.getElementById('toast-container');
  const text = document.getElementById('toast-message');
  text.textContent = message;
  
  if (isError) {
    container.firstElementChild.className = "bg-red-900 text-white p-4 rounded-xl shadow-2xl border border-red-500 flex items-start space-x-3";
  } else {
    container.firstElementChild.className = "bg-slate-900 text-white p-4 rounded-xl shadow-2xl border border-indigo-500 flex items-start space-x-3";
  }
  
  container.classList.remove('hidden');
  setTimeout(() => {
    container.classList.add('hidden');
  }, 5000);
}