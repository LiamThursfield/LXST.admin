export type App = {
    name: string;
    context: AppContext;
};

export type AppContext = 'central' | 'tenant';
