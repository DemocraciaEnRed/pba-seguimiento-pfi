/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

import '../sass/app.scss';
import './bootstrap';
import Vue from 'vue';
import http from './axios'
import globals from './globals'
import VueScrollactive from 'vue-scrollactive';
import Toasted from 'vue-toasted';

import NotificationItem from './components/NotificationItem.vue';
import FormNewReport from './components/FormNewReport.vue';
import AdminSearchUserNewAdmin from './components/AdminSearchUserNewAdmin.vue';
import ObjectiveSearchUserAddTeam from './components/ObjectiveSearchUserAddTeam.vue';
import Paginator from './components/utils/Paginator.vue';
import ErrorAlert from './components/utils/ErrorAlert.vue';
import InputIcon from './components/inputs/InputIcon.vue';
import InputTag from './components/inputs/InputTag.vue';
import InputUrls from './components/inputs/InputUrls.vue';
import InputFile from './components/inputs/InputFile.vue';
import InputAddMilestonesCreateGoal from './components/inputs/InputAddMilestonesCreateGoal.vue';
import TextEditor from './components/inputs/TextEditor.vue';
import ReportComments from './components/comments/ReportComments.vue';
import SetMapDefault from './components/maps/SetMapDefault.vue';
import DrawMap from './components/maps/DrawMap.vue';
import MapReports from './components/maps/MapReports.vue';
import PortalObjectiveStats from './components/portal/objective/Stats.vue';

window.Vue = Vue;

Vue.use(VueScrollactive);
Vue.use(Toasted, {
    iconPack: 'fontawesome',
    theme: 'toasted-primary',
    className: 'custom-toast',
    // you can pass a single action as below
    action : {
        text : 'OK',
        onClick : (e, toastObject) => {
            toastObject.goAway(0);
        }
    }
})

Vue.component('notification-item', NotificationItem);
Vue.component('form-new-report', FormNewReport);
Vue.component('admin-search-user-new-admin', AdminSearchUserNewAdmin);
Vue.component('objective-search-user-add-team', ObjectiveSearchUserAddTeam);
Vue.component('paginator', Paginator);
Vue.component('error-alert', ErrorAlert);
Vue.component('input-icon', InputIcon);
Vue.component('input-tags', InputTag);
Vue.component('input-urls', InputUrls);
Vue.component('input-file', InputFile);
Vue.component('input-add-milestones-create-goal', InputAddMilestonesCreateGoal);
Vue.component('text-editor', TextEditor);
Vue.component('report-comments', ReportComments);
Vue.component('set-map-default', SetMapDefault);
Vue.component('draw-map', DrawMap);
Vue.component('map-reports', MapReports);
Vue.component('portal-objective-stats', PortalObjectiveStats);

Vue.prototype.$http = http

Vue.mixin(globals);

/**
 * Next, we will create a fresh Vue application instance and attach it to
 * the page. Then, you may begin adding components to this application
 * or customize the JavaScript scaffolding to fit your unique needs.
 */

const app = new Vue({
    el: '#app',
});
