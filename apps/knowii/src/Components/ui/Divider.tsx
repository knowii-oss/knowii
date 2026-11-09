import { cn } from './cn';

interface DividerProps {
  className?: string;
}

export function Divider({ className }: DividerProps) {
  return <hr className={cn('my-4 h-px border-0 bg-border', className)} />;
}
