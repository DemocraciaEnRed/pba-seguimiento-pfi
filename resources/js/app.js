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
import CategoryIcon from './components/CategoryIcon.vue';
import Paginator from './components/utils/Paginator.vue';
import ErrorAlert from './components/utils/ErrorAlert.vue';
import InputFile from './components/inputs/InputFile.vue';
import InputUserAvatar from './components/inputs/InputUserAvatar.vue';
import ReportsGrid from './components/portal/home/ReportsGrid.vue';
import HomeStats from './components/portal/home/Stats.vue';
import HomeCategories from './components/portal/home/Categories.vue';
import ReportComments from './components/comments/ReportComments.vue';
import PortalReportMap from './components/portal/report/Map.vue';
import PortalObjectiveStats from './components/portal/objective/Stats.vue';
import LastObjectives from './components/portal/home/LastObjectives.vue';
import OrganizationCarrousel from './components/portal/objective/OrganizationCarrousel.vue';
import MapReports from './components/maps/MapReports.vue';
import Collapse from './components/utils/Collapse.vue';
import ReportsList from './components/report/ReportsList.vue';
import Album from './components/report/Album.vue';
import SearchObjectives from './components/portal/catalogs/objectives/Search.vue';
import SearchReports from './components/portal/catalogs/reports/Search.vue';

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
Vue.component('category-icon', CategoryIcon);
Vue.component('paginator', Paginator);
Vue.component('error-alert', ErrorAlert);
Vue.component('input-file', InputFile);
Vue.component('input-user-avatar', InputUserAvatar);
Vue.component('portal-home-reports-grid', ReportsGrid);
Vue.component('portal-home-stats', HomeStats);
Vue.component('portal-home-categories', HomeCategories);
Vue.component('report-comments', ReportComments);
Vue.component('portal-report-map', PortalReportMap);
Vue.component('portal-objective-stats', PortalObjectiveStats);
Vue.component('portal-last-objectives', LastObjectives);
Vue.component('objective-organizations-carrousel', OrganizationCarrousel);
Vue.component('map-reports', MapReports);
Vue.component('collapse', Collapse);
Vue.component('report-list', ReportsList);
Vue.component('report-album', Album);
Vue.component('search-objectives', SearchObjectives);
Vue.component('search-reports', SearchReports);

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
