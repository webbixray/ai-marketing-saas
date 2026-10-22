import { useState } from 'react';
import { Outlet } from 'react-router-dom';
import { Sidebar } from './Sidebar';
import { Header } from './Header';
import { cn } from '@utils';

interface LayoutProps {
    children?: React.ReactNode;
}

export function Layout({ children }: LayoutProps) {
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [mobileSidebarOpen, setMobileSidebarOpen] = useState(false);

    const toggleSidebar = () => {
        setSidebarOpen(!sidebarOpen);
    };

    const closeSidebar = () => {
        setSidebarOpen(false);
        setMobileSidebarOpen(false);
    };

    return (
        <div className="min-h-screen bg-slate-50 dark:bg-slate-950">
            {/* Mobile sidebar overlay */}
            {mobileSidebarOpen && (
                <div
                    className="fixed inset-0 z-40 bg-black/50 lg:hidden"
                    onClick={closeSidebar}
                    aria-hidden="true"
                />
            )}

            {/* Sidebar */}
            <Sidebar
                isOpen={sidebarOpen || mobileSidebarOpen}
                onClose={closeSidebar}
                onToggle={toggleSidebar}
            />

            {/* Header */}
            <Header
                onMenuClick={toggleSidebar}
                sidebarOpen={sidebarOpen}
            />

            {/* Main content */}
            <main
                id="main-content"
                className={cn(
                    'min-h-screen pt-16 transition-all duration-300',
                    sidebarOpen ? 'lg:pl-64' : 'lg:pl-0'
                )}
            >
                <div className="p-4 sm:p-6 lg:p-8">
                    {children || <Outlet />}
                </div>
            </main>

            {/* Global styles for focus visible */}
            <style
                dangerouslySetInnerHTML={{
                    __html: `
                *:focus-visible {
                    outline: 2px solid #3b82f6;
                    outline-offset: 2px;
                }
                
                ::selection {
                    background-color: #3b82f6;
                    color: white;
                }
                
                .scrollbar-thin {
                    scrollbar-width: thin;
                    scrollbar-color: #94a3b8 transparent;
                }
                
                .scrollbar-thin::-webkit-scrollbar {
                    width: 6px;
                    height: 6px;
                }
                
                .scrollbar-thin::-webkit-scrollbar-track {
                    background: transparent;
                }
                
                .scrollbar-thin::-webkit-scrollbar-thumb {
                    background-color: #94a3b8;
                    border-radius: 3px;
                }
                
                .scrollbar-thin::-webkit-scrollbar-thumb:hover {
                    background-color: #64748b;
                }
                `
                }}
            />
        </div>
    );
}
