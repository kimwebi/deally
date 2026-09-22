/* DeAlly — shared application entry point.
   Each concern lives in its own module; this file only wires them up. */

import './greeting.js';
import './analog-clock.js';

import { initModalSystem } from './modals.js';
import { initQuickAdd } from './quick-add.js';
import { initLiveCall } from './live-call.js';
import { initTimer } from './timer.js';
import { initToasts } from './toasts.js';
import { initWorkerMode } from './worker-mode.js';
import { initForms } from './forms.js';
import { initKbTabs } from './kb-tabs.js';
import { initSidebarMenu } from './sidebar.js';
import { initGlobalAsk } from './global-ask.js';
import { initAuthDocs } from './auth-docs.js';

document.addEventListener('DOMContentLoaded', function () {
    initModalSystem();
    initQuickAdd();
    initLiveCall();
    initTimer();
    initToasts();
    initWorkerMode();
    initForms();
    initKbTabs();
    initSidebarMenu();
    initGlobalAsk();
    initAuthDocs();
});