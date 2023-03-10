import type { ComponentStory, ComponentMeta } from '@storybook/react';
import { PageHeader } from './PageHeader';

const Story: ComponentMeta<typeof PageHeader> = {
  component: PageHeader,
  title: 'PageHeader',
};
export default Story;

const Template: ComponentStory<typeof PageHeader> = (args) => <PageHeader {...args} />;

export const Primary = Template.bind({});
Primary.args = {};
