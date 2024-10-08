import classNames from 'classnames';
import { FormEventHandler, PropsWithChildren, ReactNode } from 'react';
import SectionTitle from '@/Components/SectionTitle';

interface Props {
  title: string;
  description: string;
  renderActions?(): ReactNode;
  onSubmit: FormEventHandler;
}

export default function FormSection({ onSubmit, renderActions, title, description, children }: PropsWithChildren<Props>) {
  const hasActions = !!renderActions;

  return (
    <div className="md:grid md:grid-cols-3 md:gap-6">
      <SectionTitle title={title} description={description} />

      <div className="mt-5 md:mt-0 md:col-span-2">
        <form onSubmit={onSubmit}>
          <div
            className={classNames('px-4 py-5 bg-white sm:p-6 shadow', hasActions ? 'sm:rounded-tl-md sm:rounded-tr-md' : 'sm:rounded-md')}
          >
            <div className="grid grid-cols-6 gap-6">{children}</div>
          </div>

          {hasActions && (
            <div className="flex flex-row justify-end px-4 py-3 bg-white items-center text-right sm:px-6 shadow-md sm:rounded-b-md">
              {renderActions?.()}
            </div>
          )}
        </form>
      </div>
    </div>
  );
}
