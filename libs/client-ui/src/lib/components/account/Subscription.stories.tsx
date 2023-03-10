import type { ComponentStory, ComponentMeta } from '@storybook/react';
import { Subscription } from './Subscription';

const Story: ComponentMeta<typeof Subscription> = {
  component: Subscription,
  title: 'Subscription',
};
export default Story;

const Template: ComponentStory<typeof Subscription> = (args) => <Subscription {...args} />;

export const Primary = Template.bind({});
Primary.args = {};
