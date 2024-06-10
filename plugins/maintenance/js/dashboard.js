/*global dotclear */
'use strict';

dotclear.dcMaintenanceTaskExpired = () => {
  dotclear.jsonServicesGet('dcMaintenanceTaskExpired', (data) => {
    if (!(data.ret && data.nb !== undefined && data.nb !== dotclear.dcMaintenanceTaskExpired_Count)) {
      return;
    }
    dotclear.badge($('#dashboard-main #icons p #icon-process-maintenance-fav'), {
      id: 'dcmte',
      remove: data.nb === 0,
      value: data.nb,
      sibling: true,
      icon: true,
      type: 'info',
    });
    dotclear.badge($('#maintenance-expired'), {
      id: 'dcmte',
      remove: data.nb === 0,
      value: data.nb,
      type: 'info',
    });
    dotclear.dcMaintenanceTaskExpired_Count = data.nb;
  });
};

dotclear.ready(() => {
  // DOM ready and content loaded

  // First pass
  dotclear.dcMaintenanceTaskExpired();
  // Auto refresh requested : Set 300 seconds interval between two checks for expired maintenance task counter
  dotclear.dcMaintenanceTaskExpired_Timer = setInterval(dotclear.dcMaintenanceTaskExpired, 300 * 1000);
});
