import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { MerchantPortalSessionService } from './merchant-portal-session.service';

export const merchantPortalGuard: CanActivateFn = () => {
  const session = inject(MerchantPortalSessionService);
  const router = inject(Router);

  return session.isAuthenticated()
    ? true
    : router.createUrlTree(['/login/merchants']);
};

