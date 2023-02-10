import styles from './index.module.scss';

export function Index() {
  return (
    <div className={styles.page}>
      <div id="welcome">
        <h1>
          <span> Hello there, </span>
          Welcome 👋
        </h1>
      </div>
    </div>
  );
}

export default Index;
