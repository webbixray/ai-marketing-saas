import { Routes, Route, Navigate } from 'react-router-dom';
import { Suspense, lazy } from 'react';
import { Layout } from '@components/layout/Layout';
import { AuthLayout } from '@components/layout/AuthLayout';
import { LoadingSpinner } from '@components/ui/LoadingSpinner';
import { useAuth } from '@hooks/useAuth';

// Lazy load pages for code splitting
const Login = lazy(() => import('@pages/auth/Login').then(m => ({ default: m.Login })));
const Register = lazy(() => import('@pages/auth/Register').then(m => ({ default: m.Register })));
const ForgotPassword = lazy(() => import('@pages/auth/ForgotPassword').then(m => ({ default: m.ForgotPassword })));
const ResetPassword = lazy(() => import('@pages/auth/ResetPassword').then(m => ({ default: m.ResetPassword })));
const VerifyEmail = lazy(() => import('@pages/auth/VerifyEmail').then(m => ({ default: m.VerifyEmail })));

// Dashboard
const Dashboard = lazy(() => import('@pages/dashboard/Dashboard').then(m => ({ default: m.Dashboard })));

// Marketing
const Campaigns = lazy(() => import('@pages/marketing/Campaigns').then(m => ({ default: m.Campaigns })));
const CampaignBuilder = lazy(() => import('@pages/marketing/CampaignBuilder').then(m => ({ default: m.CampaignBuilder })));
const CampaignDetail = lazy(() => import('@pages/marketing/CampaignDetail').then(m => ({ default: m.CampaignDetail })));
const Clients = lazy(() => import('@pages/marketing/Clients').then(m => ({ default: m.Clients })));
// const ClientDetail = lazy(() => import('@pages/marketing/ClientDetail').then(m => ({ default: m.ClientDetail })));
// const CreativeStudio = lazy(() => import('@pages/marketing/CreativeStudio').then(m => ({ default: m.CreativeStudio })));
// const CopyGenerator = lazy(() => import('@pages/marketing/CopyGenerator').then(m => ({ default: m.CopyGenerator })));

// Analytics
// const AnalyticsOverview = lazy(() => import('@pages/analytics/AnalyticsOverview').then(m => ({ default: m.AnalyticsOverview })));
// const Attribution = lazy(() => import('@pages/analytics/Attribution').then(m => ({ default: m.Attribution })));
// const Forecasting = lazy(() => import('@pages/analytics/Forecasting').then(m => ({ default: m.Forecasting })));
// const AnomalyDetection = lazy(() => import('@pages/analytics/AnomalyDetection').then(m => ({ default: m.AnomalyDetection })));
// const Reports = lazy(() => import('@pages/analytics/Reports').then(m => ({ default: m.Reports })));
// const CustomReports = lazy(() => import('@pages/analytics/CustomReports').then(m => ({ default: m.CustomReports })));

// Enterprise
// const EnterpriseSSO = lazy(() => import('@pages/enterprise/EnterpriseSSO').then(m => ({ default: m.EnterpriseSSO })));
// const EnterpriseSCIM = lazy(() => import('@pages/enterprise/EnterpriseSCIM').then(m => ({ default: m.EnterpriseSCIM })));
// const EnterpriseWhiteLabel = lazy(() => import('@pages/enterprise/EnterpriseWhiteLabel').then(m => ({ default: m.EnterpriseWhiteLabel })));
// const EnterpriseAudit = lazy(() => import('@pages/enterprise/EnterpriseAudit').then(m => ({ default: m.EnterpriseAudit })));
// const EnterpriseRBAC = lazy(() => import('@pages/enterprise/EnterpriseRBAC').then(m => ({ default: m.EnterpriseRBAC })));
// const EnterpriseMarketplace = lazy(() => import('@pages/enterprise/EnterpriseMarketplace').then(m => ({ default: m.EnterpriseMarketplace })));

// Global
// const MultiRegion = lazy(() => import('@pages/global/MultiRegion').then(m => ({ default: m.MultiRegion })));
// const CDNManagement = lazy(() => import('@pages/global/CDNManagement').then(m => ({ default: m.CDNManagement })));
// const AdvancedAnalytics = lazy(() => import('@pages/global/AdvancedAnalytics').then(m => ({ default: m.AdvancedAnalytics })));
// const DataGovernance = lazy(() => import('@pages/global/DataGovernance').then(m => ({ default: m.DataGovernance })));
// const DisasterRecovery = lazy(() => import('@pages/global/DisasterRecovery').then(m => ({ default: m.DisasterRecovery })));
// const CostOptimization = lazy(() => import('@pages/global/CostOptimization').then(m => ({ default: m.CostOptimization })));

// Settings
const Settings = lazy(() => import('@pages/settings/Settings').then(m => ({ default: m.Settings })));
const Profile = lazy(() => import('@pages/settings/Profile').then(m => ({ default: m.Profile })));
const Team = lazy(() => import('@pages/settings/Team').then(m => ({ default: m.Team })));
const Billing = lazy(() => import('@pages/settings/Billing').then(m => ({ default: m.Billing })));
const APIKeys = lazy(() => import('@pages/settings/APIKeys').then(m => ({ default: m.APIKeys })));
const Notifications = lazy(() => import('@pages/settings/Notifications').then(m => ({ default: m.Notifications })));

function ProtectedRoute({ children }: { children: React.ReactNode }) {
    const { isAuthenticated, isLoading } = useAuth();

    if (isLoading) {
        return (
            <Suspense fallback={<LoadingSpinner />}>
                <div className="min-h-screen flex items-center justify-center">
                    <LoadingSpinner size="lg" />
                </div>
            </Suspense>
        );
    }

    if (!isAuthenticated) {
        return <Navigate to="/login" replace />;
    }

    return <>{children}</>;
}

function PublicRoute({ children }: { children: React.ReactNode }) {
    const { isAuthenticated, isLoading } = useAuth();

    if (isLoading) {
        return (
            <Suspense fallback={<LoadingSpinner />}>
                <div className="min-h-screen flex items-center justify-center">
                    <LoadingSpinner size="lg" />
                </div>
            </Suspense>
        );
    }

    if (isAuthenticated) {
        return <Navigate to="/dashboard" replace />;
    }

    return <>{children}</>;
}

export default function App() {
    return (
        <Suspense fallback={<LoadingSpinner />}>
            <Routes>
                {/* Public routes */}
                <Route element={<AuthLayout />}>
                    <Route path="/login" element={<PublicRoute><Login /></PublicRoute>} />
                    <Route path="/register" element={<PublicRoute><Register /></PublicRoute>} />
                    <Route path="/forgot-password" element={<PublicRoute><ForgotPassword /></PublicRoute>} />
                    <Route path="/reset-password/:token" element={<PublicRoute><ResetPassword /></PublicRoute>} />
                    <Route path="/verify-email" element={<PublicRoute><VerifyEmail /></PublicRoute>} />
                </Route>

                {/* Protected routes */}
                <Route element={<ProtectedRoute><Layout /></ProtectedRoute>}>
                    <Route path="/dashboard" element={<Dashboard />} />
                    
                    {/* Marketing */}
                    <Route path="/campaigns" element={<Campaigns />} />
                    <Route path="/campaigns/new" element={<CampaignBuilder />} />
                    <Route path="/campaigns/:id" element={<CampaignDetail />} />
                    <Route path="/campaigns/:id/edit" element={<CampaignBuilder />} />
                    <Route path="/clients" element={<Clients />} />
                    {/* <Route path="/clients/:id" element={<ClientDetail />} /> */}
                    {/* <Route path="/creative-studio" element={<CreativeStudio />} /> */}
                    {/* <Route path="/copy-generator" element={<CopyGenerator />} /> */}
                    
                    {/* Analytics */}
                    {/* <Route path="/analytics" element={<AnalyticsOverview />} /> */}
                    {/* <Route path="/analytics/attribution" element={<Attribution />} /> */}
                    {/* <Route path="/analytics/forecasting" element={<Forecasting />} /> */}
                    {/* <Route path="/analytics/anomalies" element={<AnomalyDetection />} /> */}
                    {/* <Route path="/reports" element={<Reports />} /> */}
                    {/* <Route path="/reports/custom" element={<CustomReports />} /> */}
                    
                    {/* Enterprise */}
                    {/* <Route path="/enterprise/sso" element={<EnterpriseSSO />} /> */}
                    {/* <Route path="/enterprise/scim" element={<EnterpriseSCIM />} /> */}
                    {/* <Route path="/enterprise/white-label" element={<EnterpriseWhiteLabel />} /> */}
                    {/* <Route path="/enterprise/audit" element={<EnterpriseAudit />} /> */}
                    {/* <Route path="/enterprise/rbac" element={<EnterpriseRBAC />} /> */}
                    {/* <Route path="/enterprise/marketplace" element={<EnterpriseMarketplace />} /> */}
                    
                    {/* Global */}
                    {/* <Route path="/global/regions" element={<MultiRegion />} /> */}
                    {/* <Route path="/global/cdn" element={<CDNManagement />} /> */}
                    {/* <Route path="/global/analytics" element={<AdvancedAnalytics />} /> */}
                    {/* <Route path="/global/data-governance" element={<DataGovernance />} /> */}
                    {/* <Route path="/global/disaster-recovery" element={<DisasterRecovery />} /> */}
                    {/* <Route path="/global/cost-optimization" element={<CostOptimization />} /> */}
                    
                    {/* Settings */}
                    <Route path="/settings" element={<Settings />} />
                    <Route path="/settings/profile" element={<Profile />} />
                    <Route path="/settings/team" element={<Team />} />
                    <Route path="/settings/billing" element={<Billing />} />
                    <Route path="/settings/api-keys" element={<APIKeys />} />
                    <Route path="/settings/notifications" element={<Notifications />} />
                </Route>

                {/* Redirect root to dashboard */}
                <Route path="/" element={<Navigate to="/dashboard" replace />} />
                <Route path="*" element={<Navigate to="/dashboard" replace />} />
            </Routes>
        </Suspense>
    );
}
