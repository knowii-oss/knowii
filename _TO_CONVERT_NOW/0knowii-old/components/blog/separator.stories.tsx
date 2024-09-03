import type { ComponentMeta, ComponentStory } from '@storybook/react';
import { Separator } from './separator';

const Story: ComponentMeta<typeof Separator> = {
  component: Separator,
  title: 'Separator',
};
export default Story;

const Template: ComponentStory<typeof Separator> = (args) => <Separator {...args} />;

export const Primary = Template.bind({});
Primary.args = {};
