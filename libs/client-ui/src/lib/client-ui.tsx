import styles from './client-ui.module.scss';

export function ClientUi() {
  return (
    <div className={styles['container']}>
      <h1>Welcome to ClientUi!</h1>
    </div>
  );
}

export default ClientUi;

// Theme
export { theme } from './theme';

// Components
export * from './components/common/logo';
export * from './components/layout/progress-bar';

// Hooks
export * from './hooks/use-debounce';
