// eslint-disable-next-line @typescript-eslint/no-empty-interface
interface SeparatorProps {}

export function Separator(_props: SeparatorProps) {
  return <hr className="border-b border-gray-900! dark:border-gray-400! opacity-25 my-4 py-0" />;
}

export default Separator;
